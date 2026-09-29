<?php

namespace App\Abac;

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

/**
 * Traduce una decisión del motor ABAC a lenguaje llano para el simulador: qué regla decidió,
 * en qué paso del algoritmo y, condición por condición, qué se cumplió y qué no con los valores
 * reales. Usa el mismo evaluador que el motor, así que la explicación no puede contradecirlo.
 */
class ExplicadorAbac
{
    private const GRUPOS = ['sujeto' => 'Quién', 'objeto' => 'Sobre qué', 'entorno' => 'Contexto'];

    private const ATRIBUTOS = [
        'sujeto' => [
            'roles' => 'su rol',
            'is_active' => 'su cuenta está activa',
            'suspendido' => 'está suspendido',
            'es_verificado' => 'está verificado',
            'autenticado' => 'inició sesión',
            'empresa_id' => 'pertenece a una empresa aprobada',
        ],
        'objeto' => [
            'estado' => 'el estado',
            'es_publico' => 'es público',
            'nivel_acceso' => 'el nivel de acceso',
            'invited_hacker_ids' => 'los investigadores invitados',
            'investigador_id' => 'el autor',
            'asignado_a' => 'el moderador asignado',
            'programa_id' => 'el programa',
            'empresa_id' => 'la empresa dueña',
            'programa.empresa_id' => 'la empresa dueña del programa',
            'fuera_de_fechas' => 'está fuera de sus fechas',
            'programa.fuera_de_fechas' => 'el programa está fuera de sus fechas',
            'solo_verificados' => 'es solo para verificados',
            'siguiente_en_cola' => 'es el siguiente de la cola',
            'sancion.usuario_id' => 'el sancionado',
            'sancion.estado' => 'el estado de la sanción',
            'sancion.plazo_apelacion' => 'el plazo de apelación',
            'usuario_id' => 'quien apeló',
            'id' => 'la empresa',
        ],
        'entorno' => [
            'empresa_id' => 'la empresa activa del usuario',
        ],
    ];

    /** Atributos de sí/no: la frase cuando se exige verdadero y cuando se exige falso. */
    private const BOOLEANOS = [
        'is_active' => ['su cuenta está activa', 'su cuenta está desactivada'],
        'suspendido' => ['está suspendido', 'no está suspendido'],
        'es_verificado' => ['está verificado', 'no está verificado'],
        'autenticado' => ['inició sesión', 'no inició sesión'],
        'es_publico' => ['es público', 'es privado'],
        'fuera_de_fechas' => ['está fuera de sus fechas', 'está dentro de sus fechas'],
        'programa.fuera_de_fechas' => ['el programa está fuera de sus fechas', 'el programa está dentro de sus fechas'],
        'solo_verificados' => ['es solo para verificados', 'no es solo para verificados'],
        'siguiente_en_cola' => ['es el siguiente de la cola', 'no es el siguiente de la cola'],
    ];

    private const REFERENCIAS = [
        '@sujeto.id' => 'el propio usuario',
        '@sujeto.niveles_acceso' => 'los niveles que su rango permite',
        '@sujeto.programas_moderados' => 'los programas que modera',
        '@sujeto.empresa_id' => 'la empresa del usuario',
        '@entorno.empresa_id' => 'la empresa del usuario',
        '@entorno.ahora' => 'ahora',
    ];

    public function __construct(private readonly EvaluadorCondiciones $evaluador) {}

    /**
     * @param  array<string, mixed>  $etiquetas  ['accion' => ..., 'sujeto' => ..., 'objeto' => ...]
     * @return array{resumen: string, paso: string, reglas: array<int, array<string, mixed>>, casi: array<string, mixed>|null}
     */
    public function explicar(AbacDecision $decision, ContextoAbac $contexto, array $etiquetas): array
    {
        $reglas = [];
        foreach ((array) config('abac.reglas', []) as $regla) {
            if (is_array($regla) && isset($regla['id'])) {
                $reglas[(string) $regla['id']] = $regla;
            }
        }
        $explicadas = [];

        foreach ($decision->detalle as $item) {
            $regla = $reglas[$item['regla']] ?? [];
            $explicadas[] = [
                'regla' => $item['regla'],
                'descripcion' => (string) ($regla['descripcion'] ?? $item['regla']),
                'decision' => $item['decision'],
                'coincide' => $item['coincide'],
                'condiciones' => $this->condiciones($regla, $contexto),
            ];
        }

        $quien = $etiquetas['sujeto'] ?? 'Un visitante sin sesión';
        $accion = '«'.($etiquetas['accion'] ?? $contexto->accion).'»';
        $sobre = isset($etiquetas['objeto']) ? " sobre «{$etiquetas['objeto']}»" : '';
        $decisiva = $decision->regla === null ? null : ($reglas[$decision->regla] ?? null);

        if ($decision->estaPermitida()) {
            $paso = 'permiso';
            $resumen = "{$quien} SÍ puede {$accion}{$sobre}. Lo permite esta regla: ".($decisiva['descripcion'] ?? $decision->regla);
        } elseif ($decisiva !== null) {
            $paso = 'denegacion';
            $resumen = "{$quien} NO puede {$accion}{$sobre}. Lo impide esta regla: {$decisiva['descripcion']} Una regla que deniega gana siempre.";
        } else {
            $paso = 'por_defecto';
            $resumen = "{$quien} NO puede {$accion}{$sobre}: ninguna regla se lo permite, así que el sistema lo deniega por defecto.";
        }

        return [
            'resumen' => $resumen,
            'paso' => $paso,
            'reglas' => $explicadas,
            'casi' => $paso === 'por_defecto' ? $this->reglaMasCercana($explicadas) : null,
        ];
    }

    /**
     * La regla de permiso a la que le faltó menos para cumplirse: dice qué habría que cambiar.
     *
     * @param  array<int, array<string, mixed>>  $reglas
     * @return array<string, mixed>|null
     */
    private function reglaMasCercana(array $reglas): ?array
    {
        $candidatas = array_filter($reglas, fn (array $r): bool => $r['decision'] === 'permitir' && $r['condiciones'] !== []);
        if ($candidatas === []) {
            return null;
        }

        // Primero las reglas pensadas para este usuario (su «Quién» se cumple): decirle a una
        // empresa que le faltó «ser administrador» no explica nada. Después, la que menos falla.
        usort($candidatas, fn (array $a, array $b): int => [$this->fallos($a, 'Quién'), $this->fallos($a)] <=> [$this->fallos($b, 'Quién'), $this->fallos($b)]);
        $mejor = $candidatas[0];

        return [
            'regla' => $mejor['regla'],
            'descripcion' => $mejor['descripcion'],
            'faltan' => array_values(array_filter($mejor['condiciones'], fn (array $c): bool => ! $c['cumple'])),
        ];
    }

    /** @param  array<string, mixed>  $regla */
    private function fallos(array $regla, ?string $grupo = null): int
    {
        return count(array_filter($regla['condiciones'], fn (array $c): bool => ! $c['cumple'] && ($grupo === null || $c['grupo'] === $grupo)));
    }

    /**
     * @param  array<string, mixed>  $regla
     * @return array<int, array{grupo: string, texto: string, actual: string, cumple: bool}>
     */
    private function condiciones(array $regla, ContextoAbac $contexto): array
    {
        $resultado = [];

        foreach (self::GRUPOS as $grupo => $nombreGrupo) {
            foreach ((array) ($regla[$grupo] ?? []) as $atributo => $operadores) {
                $operadores = EvaluadorCondiciones::normalizarOperadores((array) $operadores);
                $cumple = $this->evaluador->cumple([$atributo => $operadores], $grupo, $contexto);
                [$existe, $valor] = $contexto->valor($grupo, (string) $atributo);

                $partes = [];
                foreach ($operadores as $operador => $esperado) {
                    $partes[] = $this->textoOperador((string) $operador, $esperado, $grupo, (string) $atributo);
                }

                $resultado[] = [
                    'grupo' => $nombreGrupo,
                    'texto' => implode(' y ', $partes),
                    'actual' => match (true) {
                        ! $existe => 'sin dato (no hay objeto o no aplica)',
                        // «su cuenta está desactivada (ahora: sí)» confunde: se dice la frase verdadera.
                        is_bool($valor) && isset(self::BOOLEANOS[$atributo]) => self::BOOLEANOS[$atributo][$valor ? 0 : 1],
                        default => $this->valorLegible($valor, (string) $atributo),
                    },
                    'cumple' => $cumple,
                ];
            }
        }

        return $resultado;
    }

    private function textoOperador(string $operador, mixed $esperado, string $grupo, string $atributo): string
    {
        $nombre = self::ATRIBUTOS[$grupo][$atributo] ?? $atributo;
        $esBooleano = is_bool($esperado) && in_array($operador, ['=', '=='], true);

        if ($esBooleano) {
            [$si, $no] = self::BOOLEANOS[$atributo] ?? [$nombre, "no {$nombre}"];

            return $esperado ? $si : $no;
        }

        $valor = $this->esperadoLegible($esperado);

        return match ($operador) {
            '=', '==' => "{$nombre} es {$valor}",
            '!=' => "{$nombre} no es {$valor}",
            'in' => "{$nombre} está entre {$valor}",
            'not_in' => "{$nombre} no está entre {$valor}",
            'contains' => ($atributo === 'roles' ? 'tiene el rol' : "{$nombre} incluye")." {$valor}",
            '>' => "{$nombre} es posterior/mayor que {$valor}",
            '>=' => "{$nombre} es igual o posterior a {$valor}",
            '<' => "{$nombre} es anterior/menor que {$valor}",
            '<=' => "{$nombre} es igual o anterior a {$valor}",
            'is_null' => "{$nombre}: ninguno",
            'is_not_null' => $grupo === 'sujeto' && $atributo === 'empresa_id' ? $nombre : "{$nombre}: tiene uno",
            default => "{$nombre} {$operador} {$valor}",
        };
    }

    private function esperadoLegible(mixed $esperado): string
    {
        if (is_string($esperado) && isset(self::REFERENCIAS[$esperado])) {
            return self::REFERENCIAS[$esperado];
        }

        return $this->valorLegible($esperado);
    }

    private function valorLegible(mixed $valor, string $atributo = ''): string
    {
        if ($valor === null) {
            return 'ninguno';
        }
        if (is_bool($valor)) {
            return $valor ? 'sí' : 'no';
        }
        if (is_array($valor)) {
            return $valor === [] ? 'ninguno' : implode(', ', array_map(fn ($v) => $this->valorLegible($v), $valor));
        }
        // Los ids de empresas y programas también, con su nombre.
        if (in_array($atributo, ['empresa_id', 'programa.empresa_id'], true) && is_numeric($valor)) {
            $empresa = Empresa::query()->find((int) $valor);

            return $empresa->nombre_comercial ?? $empresa->razon_social ?? "empresa #{$valor}";
        }
        if ($atributo === 'programa_id' && is_numeric($valor)) {
            return Programa::query()->whereKey((int) $valor)->value('nombre') ?? "programa #{$valor}";
        }
        // Los ids de personas se muestran con su nombre: «el autor es Ana Torres», no «es 15».
        if (in_array($atributo, ['investigador_id', 'asignado_a', 'usuario_id', 'sancion.usuario_id'], true) && is_numeric($valor)) {
            return User::query()->whereKey((int) $valor)->value('name') ?? "usuario #{$valor}";
        }

        return (string) $valor;
    }
}
