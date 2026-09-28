<?php

namespace App\Abac;

use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Services\Reputacion\Rangos;
use App\Support\CachePorPeticion;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Resuelve los atributos de sujeto, objeto y entorno que alimentan las reglas ABAC.
 *
 * Normaliza valores de Eloquent (enums, fechas, booleanos, numéricos) a
 * escalares comparables y permite recorrer rutas de relación en el objeto
 * (p. ej. `programa.estado`, `sancion.plazo_apelacion`).
 */
class AtributosAbac
{
    /**
     * Atributos del sujeto (usuario autenticado o invitado).
     *
     * Un mismo request evalúa el ABAC varias veces (FormRequest, controlador, un permiso por
     * botón) y cada cálculo cuesta ~5 consultas: se resuelve una vez por petición (ver
     * CachePorPeticion), con las columnas del usuario en la clave por si cambian en memoria.
     *
     * @return array{autenticado: bool, id: int|null, roles: array<int, string>, reputation_score: int, niveles_acceso: array<int, string>, programas_moderados: array<int, int>, suspendido: bool, empresa_id: int|null, rol_empresa: string|null}
     */
    public function sujeto(?User $usuario): array
    {
        if ($usuario === null) {
            return ['autenticado' => false, 'id' => null, 'roles' => [], 'reputation_score' => 0, 'niveles_acceso' => [], 'programas_moderados' => [], 'suspendido' => false, 'empresa_id' => null, 'rol_empresa' => null];
        }

        return CachePorPeticion::recordar(
            'abac.sujeto:'.$usuario->id.':'.md5((string) json_encode($usuario->getAttributes())),
            fn (): array => $this->resolverSujeto($usuario),
        );
    }

    /**
     * @return array{autenticado: bool, id: int|null, roles: array<int, string>, reputation_score: int, niveles_acceso: array<int, string>, programas_moderados: array<int, int>, suspendido: bool, empresa_id: int|null, rol_empresa: string|null}
     */
    private function resolverSujeto(User $usuario): array
    {
        $roles = [];

        foreach ($usuario->roles as $rol) {
            $roles[] = (string) $rol->slug;
        }

        $puntos = (int) ($usuario->reputation_score ?? 0);
        $empresa = $usuario->empresaActiva();
        $suspendido = $usuario->suspensionActiva() !== null;

        return [
            'autenticado' => true,
            'id' => $usuario->id,
            'roles' => $roles,
            // Cuenta desactivada por el administrador (la suspensión va aparte, en `suspendido`).
            'is_active' => (bool) ($usuario->is_active ?? true),
            'reputation_score' => $puntos,
            // Niveles de programa a los que su rango de reputación le da acceso.
            'niveles_acceso' => app(Rangos::class)->nivelesAccesibles($puntos),
            // Un moderador solo actúa sobre los programas que se le asignaron.
            'programas_moderados' => in_array('moderador', $roles, true) ? $usuario->idsProgramasModerados() : [],
            'suspendido' => $suspendido,
            // Mismo criterio que User::esVerificado(), sin repetir la consulta de suspensión y sin
            // mirar sanciones cuando aún no llega a la meta de informes.
            'es_verificado' => ! $suspendido
                && $usuario->cantidadReportesValidados() >= User::INFORMES_PARA_VERIFICARSE
                && ! $usuario->tieneSancionReciente(),
            // La empresa a la que pertenece (una sola) y su papel en ella: propietario (los investigadores no pertenecen a empresas).
            'empresa_id' => $empresa?->id,
            'rol_empresa' => $empresa?->pivot->rol_interno,
        ];
    }

    /**
     * Atributos planos del objeto (modelo o arreglo).
     *
     * @return array<string, mixed>
     */
    public function objeto(mixed $objeto): array
    {
        if ($objeto instanceof Model) {
            return $this->modelo($objeto);
        }

        if (is_array($objeto)) {
            return $this->normalizarArreglo($objeto);
        }

        return [];
    }

    /**
     * Atributos del entorno: por defecto `app_env`, `ahora` y la
     * autenticación; se pueden ampliar por petición.
     *
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    public function entorno(array $extras = []): array
    {
        return $this->normalizarArreglo([
            'app_env' => config('app.env'),
            'ahora' => now()->format('Y-m-d H:i:s'),
            ...$extras,
        ]);
    }

    /**
     * Resuelve un atributo de una ruta de relación del modelo (p. ej. `programa.estado`).
     *
     * @return array{0: bool, 1: mixed} [existe, valor]
     */
    public function valorRutaModelo(Model $modelo, string $ruta): array
    {
        $partes = explode('.', $ruta);
        $ultimo = array_pop($partes);
        $actual = $modelo;

        foreach ($partes as $relacion) {
            if (! method_exists($actual, $relacion)) {
                return [false, null];
            }

            $actual = $actual->{$relacion}();

            if ($actual instanceof Relation) {
                $actual = $actual->getResults();
            }

            if (! $actual instanceof Model) {
                return [false, null];
            }
        }

        if (array_key_exists($ultimo, $actual->getAttributes())) {
            $casts = $actual->getCasts();

            return [true, $this->normalizarValor($actual->getAttributes()[$ultimo], $casts[$ultimo] ?? null)];
        }

        if (isset($actual->{$ultimo}) || method_exists($actual, 'get'.Str::studly($ultimo).'Attribute')) {
            return [true, $this->normalizarValor($actual->{$ultimo})];
        }

        return [false, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelo(Model $modelo): array
    {
        $atributos = [];
        $casts = $modelo->getCasts();

        foreach ($modelo->getAttributes() as $clave => $valor) {
            $atributos[$clave] = $this->normalizarValor($valor, $casts[$clave] ?? null);
        }

        return [...$atributos, ...$this->calculados($modelo)];
    }

    /**
     * Atributos que no son columnas y cuestan consultas. Una página evalúa varias acciones sobre
     * el mismo informe o programa, así que se calculan una vez por petición.
     *
     * @return array<string, mixed>
     */
    private function calculados(Model $modelo): array
    {
        if (! $modelo instanceof Programa && ! $modelo instanceof Reporte) {
            return [];
        }

        // Con sus columnas en la clave: si el modelo cambia en memoria, se recalcula.
        return CachePorPeticion::recordar(
            'abac.objeto:'.$modelo::class.':'.$modelo->getKey().':'.md5((string) json_encode($modelo->getAttributes())),
            fn (): array => $modelo instanceof Programa
                ? ['invited_hacker_ids' => $modelo->invited_hacker_ids, 'fuera_de_fechas' => $modelo->fuera_de_fechas]
                // Cola por orden de llegada: el moderador solo abre el siguiente informe sin revisor.
                : ['siguiente_en_cola' => $modelo->esSiguienteEnCola()],
        );
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function normalizarArreglo(array $valores): array
    {
        $resolvido = [];

        foreach ($valores as $clave => $valor) {
            $resolvido[$clave] = $this->normalizarValor($valor);
        }

        return $resolvido;
    }

    /**
     * @return mixed valor normalizado y comparable
     */
    public function normalizarValor(mixed $valor, ?string $cast = null): mixed
    {
        if ($valor === null) {
            return null;
        }

        if ($valor instanceof BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_array($valor)) {
            return array_map(fn (mixed $v): mixed => $this->normalizarValor($v), $valor);
        }

        if (is_string($cast) && enum_exists($cast)) {
            /** @var class-string<BackedEnum> $cast */
            return $cast::from((string) $valor)->value;
        }

        $cast = $cast === null ? null : strtolower($cast);

        if ($cast === 'boolean' || $cast === 'bool') {
            return $this->aBooleano($valor);
        }

        if ($cast === 'integer' || $cast === 'int') {
            return (int) $valor;
        }

        if ($cast === 'float' || $cast === 'double' || ($cast !== null && str_starts_with($cast, 'decimal'))) {
            return (float) $valor;
        }

        if (
            $cast !== null
            && (str_starts_with($cast, 'datetime') || str_starts_with($cast, 'date') || $cast === 'timestamp')
        ) {
            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        }

        return $valor;
    }

    private function aBooleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_int($valor)) {
            return $valor !== 0;
        }

        if (is_string($valor)) {
            $bajo = strtolower(trim($valor));

            return $bajo !== '' && ! in_array($bajo, ['0', 'false', 'f', 'no', 'n'], true);
        }

        return (bool) $valor;
    }
}
