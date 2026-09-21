<?php

namespace App\Services\Reputacion;

use App\Models\Apelacion;
use App\Models\ApelacionEvento;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Registro de control de las apelaciones: quién hizo cada paso, con qué rol, cuándo y desde dónde.
 *
 * Cada paso guarda una «huella»: el SHA-256 de sus datos junto con la huella del paso anterior.
 * Así, si alguien modifica o borra un paso ya registrado, la cadena deja de cuadrar y
 * {@see self::verificar()} lo detecta.
 */
class TrazaApelaciones
{
    public const PRESENTADA = 'presentada';

    public const APROBADA = 'aprobada';

    public const RECHAZADA = 'rechazada';

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(Apelacion $apelacion, string $tipo, ?User $actor, ?string $nota = null, array $datos = []): ApelacionEvento
    {
        $anterior = ApelacionEvento::query()
            ->where('apelacion_id', $apelacion->id)
            ->orderByDesc('id')
            ->value('huella');

        $momento = now();

        $atributos = [
            'apelacion_id' => $apelacion->id,
            'tipo' => $tipo,
            'actor_id' => $actor?->id,
            'actor_nombre' => $actor?->name,
            'actor_rol' => $actor === null ? null : self::rolPrincipal($actor),
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
            'nota' => $nota === '' ? null : $nota,
            'datos' => $datos === [] ? null : $datos,
            'huella_anterior' => $anterior,
            'created_at' => $momento,
        ];

        $atributos['huella'] = $this->calcular($atributos, $momento->format('Y-m-d H:i:s'));

        return ApelacionEvento::query()->create($atributos);
    }

    /** ¿La cadena de huellas de la apelación está íntegra? */
    public function verificar(Apelacion $apelacion): bool
    {
        $anterior = null;

        foreach (ApelacionEvento::query()->where('apelacion_id', $apelacion->id)->orderBy('id')->get() as $evento) {
            if ($evento->huella_anterior !== $anterior) {
                return false;
            }

            $esperada = $this->calcular([
                'apelacion_id' => $evento->apelacion_id,
                'tipo' => $evento->tipo,
                'actor_id' => $evento->actor_id,
                'actor_rol' => $evento->actor_rol,
                'nota' => $evento->nota,
                'datos' => $evento->datos,
                'huella_anterior' => $evento->huella_anterior,
            ], $evento->created_at->format('Y-m-d H:i:s'));

            if (! hash_equals($esperada, (string) $evento->huella)) {
                return false;
            }

            $anterior = $evento->huella;
        }

        return true;
    }

    /** El rol que mejor describe al usuario en una decisión de control. */
    public static function rolPrincipal(User $usuario): string
    {
        $roles = $usuario->roles()->pluck('slug')->all();

        foreach (['administrador', 'moderador', 'empresa', 'investigador'] as $rol) {
            if (in_array($rol, $roles, true)) {
                return $rol;
            }
        }

        return 'sistema';
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function calcular(array $a, string $momento): string
    {
        $datos = $a['datos'] ?? null;

        if (is_array($datos)) {
            $datos = $this->ordenar($datos);
        }

        return hash('sha256', implode('|', [
            $a['apelacion_id'],
            $a['tipo'],
            $a['actor_id'] ?? '',
            $a['actor_rol'] ?? '',
            $a['nota'] ?? '',
            $datos === null ? '' : json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $a['huella_anterior'] ?? '',
            $momento,
        ]));
    }

    /**
     * @param  array<int|string, mixed>  $valores
     * @return array<int|string, mixed>
     */
    private function ordenar(array $valores): array
    {
        ksort($valores);

        foreach ($valores as $clave => $valor) {
            if (is_array($valor)) {
                $valores[$clave] = $this->ordenar($valor);
            }
        }

        return $valores;
    }
}
