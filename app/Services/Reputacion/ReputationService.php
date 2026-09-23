<?php

namespace App\Services\Reputacion;

use App\Enums\EstadoApelacion;
use App\Enums\EstadoSancion;
use App\Enums\GravedadSancion;
use App\Enums\TipoEventoReporte;
use App\Models\Apelacion;
use App\Models\Auditoria;
use App\Models\EntradaReputacion;
use App\Models\EventoReporte;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Motor de reputación de la plataforma.
 *
 * Garantiza que el ledger (`ledger_reputacion`) sea el único punto de verdad
 * del saldo: `users.reputation_score` es una vista derivada que siempre se
 * reconcilia al asentar una entrada. El ledger es inmutable (no admite
 * actualizaciones ni borrados).
 *
 * También orquesta el ciclo de vida de las sanciones y sus apelaciones.
 */
class ReputationService
{
    public function puntosInicial(): int
    {
        return (int) config('reputacion.puntos_inicial', 0);
    }

    /**
     * Saldo actual según el ledger (fuente de verdad), sin depender de la
     * columna sincronizada.
     */
    public function saldo(User|int $usuario): int
    {
        return (int) EntradaReputacion::query()
            ->where('usuario_id', $this->usuarioId($usuario))
            ->sum('puntos');
    }

    /**
     * Recalcula `users.reputation_score` a partir del ledger y devuelve el
     * nuevo saldo.
     */
    public function sincronizar(User|int $usuario): int
    {
        $usuarioId = $this->usuarioId($usuario);
        $saldo = $this->saldo($usuarioId);

        User::query()->whereKey($usuarioId)->update(['reputation_score' => $saldo]);

        return $saldo;
    }

    /**
     * Recalcula la reputación de todos los usuarios a partir del ledger.
     *
     * @return int número de usuarios reconciliados
     */
    public function recalcular(): int
    {
        $actualizados = 0;

        User::query()->select(['id'])->each(function (User $usuario) use (&$actualizados): void {
            $this->sincronizar($usuario);
            $actualizados++;
        });

        return $actualizados;
    }

    /**
     * Asienta una entrada en el ledger y sincroniza el saldo en la misma
     * transacción. Es el único punto de escritura de la reputación.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws InvalidArgumentException si los puntos son cero
     */
    public function asentar(
        int $usuarioId,
        int $puntos,
        string $motivo,
        ?Reporte $reporte = null,
        ?Sancion $sancion = null,
        ?Apelacion $apelacion = null,
        array $metadata = [],
    ): EntradaReputacion {
        if ($puntos === 0) {
            throw new InvalidArgumentException('El ledger no admite asientos de 0 puntos.');
        }

        $rangos = app(Rangos::class);
        $rangoAntes = $rangos->deReputacion($this->saldo($usuarioId));

        $entrada = DB::transaction(function () use ($usuarioId, $puntos, $motivo, $reporte, $sancion, $apelacion, $metadata): EntradaReputacion {
            $entrada = EntradaReputacion::query()->create([
                'usuario_id' => $usuarioId,
                'puntos' => $puntos,
                'motivo' => $motivo,
                'reporte_id' => $reporte?->id,
                'sancion_id' => $sancion?->id,
                'apelacion_id' => $apelacion?->id,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $nuevoSaldo = $this->saldo($usuarioId);
            User::query()->whereKey($usuarioId)->update(['reputation_score' => $nuevoSaldo]);

            return $entrada;
        });

        $this->notificador()->rangoCambiado($usuarioId, $rangoAntes, $rangos->deReputacion($this->saldo($usuarioId)));

        return $entrada;
    }

    /**
     * Otorga los puntos configurados para un evento de reputación positiva
     * (p. ej. `reporte_validado`, `reporte_resuelto`).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function otorgarPuntosEvento(User|int $usuario, string $evento, ?Reporte $reporte = null, array $metadata = []): ?EntradaReputacion
    {
        $puntos = (int) config("reputacion.puntos.{$evento}");

        if ($puntos === 0) {
            return null;
        }

        return $this->asentar(
            $this->usuarioId($usuario),
            $puntos,
            $evento,
            $reporte,
            metadata: $metadata,
        );
    }

    /**
     * Aplica una sanción: crea el registro, asienta la penalización en el
     * ledger (ya proporcional a la reincidencia), registra el evento en el
     * timeline del reporte (si existe) y audita la acción.
     *
     * @param  array<string, mixed>  $metadata
     * @param  int|null  $puntos  penalización base (por defecto la de config)
     */
    public function aplicarSancion(
        User|int $usuario,
        string $motivo,
        GravedadSancion $gravedad,
        ?Reporte $reporte = null,
        array $metadata = [],
        ?int $puntos = null,
        ?User $aplicadaPor = null,
    ): Sancion {
        $usuarioId = $this->usuarioId($usuario);
        $base = $puntos ?? (int) config("reputacion.penalizacion.{$gravedad->value}", -25);
        $multiplicador = $this->multiplicadorReincidencia($usuarioId);
        $penalizacion = -abs((int) round($base * $multiplicador));

        [$suspensionDesde, $suspensionHasta] = $this->ventanaSuspension($gravedad);

        $sancion = DB::transaction(function () use ($usuarioId, $motivo, $gravedad, $reporte, $metadata, $penalizacion, $multiplicador, $suspensionDesde, $suspensionHasta, $aplicadaPor): Sancion {
            $sancion = Sancion::query()->create([
                'usuario_id' => $usuarioId,
                'reporte_id' => $reporte?->id,
                'aplicada_por' => $aplicadaPor?->id,
                'motivo' => $motivo,
                'gravedad' => $gravedad->value,
                'puntos' => $penalizacion,
                'estado' => EstadoSancion::Aplicada->value,
                'suspension_desde' => $suspensionDesde,
                'suspension_hasta' => $suspensionHasta,
                'plazo_apelacion' => now()->addDays((int) config('reputacion.plazo_apelacion_dias', 7)),
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $this->asentar(
                $usuarioId,
                $penalizacion,
                $motivo,
                $reporte,
                $sancion,
                metadata: $metadata + ['gravedad' => $gravedad->value],
            );

            if ($reporte !== null) {
                EventoReporte::query()->create([
                    'reporte_id' => $reporte->id,
                    'tipo' => TipoEventoReporte::Sancion->value,
                    'nota' => "Sanción {$gravedad->value}: {$motivo}.",
                    'datos' => [
                        'sancion_id' => $sancion->id,
                        'puntos' => $penalizacion,
                        'motivo' => $motivo,
                        'gravedad' => $gravedad->value,
                    ],
                ]);
            }

            $this->auditar($usuarioId, 'sancion.aplicada', $sancion, [
                'motivo' => $motivo,
                'gravedad' => $gravedad->value,
                'puntos' => $penalizacion,
                'multiplicador' => $multiplicador,
                'reporte_id' => $reporte?->id,
                'aplicada_por' => $aplicadaPor?->id,
            ]);

            return $sancion;
        });

        $this->notificador()->sancionAplicada($sancion);

        return $sancion;
    }

    /**
     * Revoca una sanción vigente: la deja en estado `revocada` y asienta en el
     * ledger la reversión exacta de los puntos, devolviendo al usuario a su
     * saldo previo.
     */
    public function revocarSancion(Sancion $sancion, string $nota = '', bool $avisar = true): void
    {
        if (! in_array($sancion->estado, [EstadoSancion::Aplicada, EstadoSancion::Apelada], true)) {
            throw new InvalidArgumentException('Solo se pueden revocar sanciones en estado aplicada o apelada.');
        }

        DB::transaction(function () use ($sancion, $nota): void {
            $metadata = (array) ($sancion->metadata ?? []);
            $metadata['revocada'] = ['en' => now()->toDateTimeString(), 'nota' => $nota];

            $sancion->update([
                'estado' => EstadoSancion::Revocada->value,
                'metadata' => $metadata,
            ]);

            $this->asentar(
                (int) $sancion->usuario_id,
                abs((int) $sancion->puntos),
                'reversion_sancion',
                $sancion->reporte,
                $sancion,
                metadata: ['sancion' => (int) $sancion->id, 'nota' => $nota],
            );

            $this->auditar((int) $sancion->usuario_id, 'sancion.revocada', $sancion, ['nota' => $nota]);
        });

        if ($avisar) {
            $this->notificador()->sancionRevocada($sancion);
        }
    }

    /**
     * Registra una apelación contra una sanción vigente del usuario, dentro
     * del plazo configurado y sin otra apelación pendiente.
     *
     * @param  array<string, mixed>  $evidencia
     *
     * @throws InvalidArgumentException si la sanción no es apelable
     */
    public function crearApelacion(
        Sancion $sancion,
        User|int $usuario,
        string $motivo,
        array $evidencia = [],
    ): Apelacion {
        $usuarioId = $this->usuarioId($usuario);

        if (! in_array($sancion->estado, [EstadoSancion::Aplicada, EstadoSancion::Apelada], true)) {
            throw new InvalidArgumentException('La sanción no es apelable: su estado ya no está vigente.');
        }

        if ((int) $sancion->usuario_id !== $usuarioId) {
            throw new InvalidArgumentException('La sanción pertenece a otro usuario.');
        }

        if ($sancion->plazo_apelacion === null || $sancion->plazo_apelacion->lt(now())) {
            throw new InvalidArgumentException('El plazo para apelar esta sanción ya venció.');
        }

        $pendiente = Apelacion::query()
            ->where('sancion_id', $sancion->id)
            ->where('estado', EstadoApelacion::Pendiente->value)
            ->exists();

        if ($pendiente) {
            throw new InvalidArgumentException('Ya existe una apelación pendiente para esta sanción.');
        }

        $rechazada = Apelacion::query()
            ->where('sancion_id', $sancion->id)
            ->where('estado', EstadoApelacion::Rechazada->value)
            ->exists();
        if ($rechazada) {
            throw new InvalidArgumentException('Esta sanción ya fue apelada y la apelación se rechazó: no se puede apelar de nuevo.');
        }

        $apelacion = DB::transaction(function () use ($sancion, $usuarioId, $motivo, $evidencia): Apelacion {
            $apelacion = Apelacion::query()->create([
                'sancion_id' => $sancion->id,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
                'evidencia' => $evidencia === [] ? null : $evidencia,
                'estado' => EstadoApelacion::Pendiente->value,
            ]);

            $sancion->update(['estado' => EstadoSancion::Apelada->value]);

            $this->auditar($usuarioId, 'apelacion.creada', $apelacion, ['sancion_id' => $sancion->id]);

            $this->traza()->registrar($apelacion, TrazaApelaciones::PRESENTADA, User::query()->find($usuarioId), $motivo, [
                'sancion_id' => $sancion->id,
                'sancion_gravedad' => $sancion->gravedad->value,
                'sancion_puntos' => $sancion->puntos,
                'sancion_aplicada_por' => $sancion->aplicada_por,
            ]);

            return $apelacion;
        });

        $this->notificador()->apelacionPresentada($apelacion);

        return $apelacion;
    }

    /**
     * Resuelve una apelación pendiente. Si se aprueba, la sanción se revoca y
     * el ledger revierte los puntos; si se rechaza, la sanción vuelve a
     * estado `aplicada`.
     */
    public function resolverApelacion(
        Apelacion $apelacion,
        bool $aprobada,
        User|int $resolutor,
        string $nota = '',
    ): void {
        if ($apelacion->estado !== EstadoApelacion::Pendiente) {
            throw new InvalidArgumentException('La apelación ya fue resuelta.');
        }

        $resolutorId = $this->usuarioId($resolutor);

        DB::transaction(function () use ($apelacion, $aprobada, $resolutorId, $nota): void {
            $apelacion->update([
                'estado' => $aprobada ? EstadoApelacion::Aprobada->value : EstadoApelacion::Rechazada->value,
                'resuelta_por' => $resolutorId,
                'resuelta_en' => now(),
                'nota_resolucion' => $nota === '' ? null : $nota,
            ]);

            if ($aprobada) {
                $this->revocarSancion($apelacion->sancion, $nota, avisar: false);
            } else {
                $apelacion->sancion->update(['estado' => EstadoSancion::Aplicada->value]);
            }

            $this->auditar($resolutorId, 'apelacion.resuelta', $apelacion, [
                'aprobada' => $aprobada,
                'nota' => $nota,
            ]);

            $this->traza()->registrar(
                $apelacion,
                $aprobada ? TrazaApelaciones::APROBADA : TrazaApelaciones::RECHAZADA,
                User::query()->find($resolutorId),
                $nota,
                // Solo un administrador puede resolver la apelación de una sanción que él mismo aplicó: queda anotado.
                ['mismo_que_sanciono' => (int) $apelacion->sancion->aplicada_por === $resolutorId],
            );
        });

        $this->notificador()->apelacionResuelta($apelacion, $aprobada, User::query()->find($resolutorId));
    }

    /**
     * Las entradas del ledger del usuario, en orden cronológico inverso.
     *
     * @return Collection<int, EntradaReputacion>
     */
    public function historial(User|int $usuario): Collection
    {
        return EntradaReputacion::query()
            ->with(['reporte', 'sancion', 'apelacion'])
            ->where('usuario_id', $this->usuarioId($usuario))
            ->latest('created_at')
            ->get();
    }

    /**
     * Multiplicador proporcional a la reincidencia: cada sanción vigente previa
     * incrementa la penalización base hasta un máximo configurado.
     */
    private function multiplicadorReincidencia(int $usuarioId): float
    {
        $vigentes = Sancion::query()
            ->where('usuario_id', $usuarioId)
            ->whereIn('estado', [EstadoSancion::Aplicada->value, EstadoSancion::Apelada->value])
            ->count();

        if ($vigentes === 0) {
            return 1.0;
        }

        $factor = (float) config('reputacion.cheat.recidivismo.multiplicador', 0.5);
        $maximo = (float) config('reputacion.cheat.recidivismo.max_multiplicador', 3.0);

        return min($maximo, 1.0 + $vigentes * $factor);
    }

    /**
     * @return array{CarbonImmutable|null, CarbonImmutable|null} [desde, hasta]
     */
    private function ventanaSuspension(GravedadSancion $gravedad): array
    {
        $dias = (int) data_get(config('reputacion.suspension', []), "{$gravedad->value}.dias", 0);

        if ($dias <= 0) {
            return [null, null];
        }

        return [now(), now()->addDays($dias)];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    /**
     * ¿Puede el usuario apelar esta sanción ahora? Vigente, dentro del plazo y sin ninguna
     * apelación previa: una sanción se apela una sola vez.
     */
    public function puedeApelar(Sancion $sancion): bool
    {
        if ($sancion->estado !== EstadoSancion::Aplicada) {
            return false;
        }

        if ($sancion->plazo_apelacion === null || $sancion->plazo_apelacion->lt(now())) {
            return false;
        }

        return ! Apelacion::query()->where('sancion_id', $sancion->id)->exists();
    }

    private function notificador(): Notificador
    {
        return app(Notificador::class);
    }

    private function traza(): TrazaApelaciones
    {
        return app(TrazaApelaciones::class);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function auditar(int|string|null $usuarioId, string $accion, ?Model $entidad = null, array $detalle = []): void
    {
        Auditoria::query()->create([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'entidad_type' => $entidad?->getMorphClass(),
            'entidad_id' => $entidad?->getKey() !== null ? (int) $entidad->getKey() : null,
            'detalle' => $detalle === [] ? null : $detalle,
        ]);
    }

    private function usuarioId(User|int $usuario): int
    {
        return $usuario instanceof User ? (int) $usuario->id : $usuario;
    }
}
