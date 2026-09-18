<?php

namespace App\Console\Commands;

use App\Models\Apelacion;
use App\Models\User;
use App\Services\Reputacion\CheatDetectionService;
use App\Services\Reputacion\ReputationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Audita la reputación de los usuarios: config, saldos vs ledger, sanciones
 * vigentes y apelaciones. Con `--analizar` ejecuta los detectores de trampas
 * en modo lectura (sin aplicar sanciones).
 *
 * Ejemplos:
 *   php artisan reputacion:audit                    -> resumen global
 *   php artisan reputacion:audit --usuario=3         -> detalle de un usuario
 *   php artisan reputacion:audit --analizar          -> detectores de trampas
 *   php artisan reputacion:audit --usuario=3 --analizar
 */
class ReputacionAuditCommand extends Command
{
    protected $signature = 'reputacion:audit
        {--usuario= : ID del usuario a auditar}
        {--analizar : Ejecuta los detectores de trampas (solo análisis, sin aplicar)}';

    protected $description = 'Audita la reputación, el ledger y los detectores de trampas';

    public function handle(CheatDetectionService $cheat, ReputationService $reputacion): int
    {
        if ($this->option('analizar')) {
            return $this->analizar($cheat);
        }

        $usuarioId = $this->option('usuario');

        if ($usuarioId === null) {
            return $this->resumen($reputacion);
        }

        return $this->detalle((int) $usuarioId, $reputacion);
    }

    private function resumen(ReputationService $reputacion): int
    {
        $usuarios = User::query()->withCount('entradasReputacion')->orderBy('id')->get();

        $this->line('=== Reputación ('.count($usuarios).' usuarios) ===');
        $this->line('puntos_inicial: '.$reputacion->puntosInicial());
        $this->line(sprintf(
            'penalizacion: leve=%d media=%d grave=%d',
            (int) config('reputacion.penalizacion.leve'),
            (int) config('reputacion.penalizacion.media'),
            (int) config('reputacion.penalizacion.grave'),
        ));
        $this->newLine();

        if ($usuarios->isEmpty()) {
            $this->warn('No hay usuarios registrados.');

            return self::FAILURE;
        }

        foreach ($usuarios as $usuario) {
            $this->line(sprintf(
                '#%d %-32s saldo=%6d ledger=%6d entradas=%d',
                $usuario->id,
                $usuario->email,
                $usuario->reputation_score,
                $reputacion->saldo($usuario),
                $usuario->entradas_reputacion_count,
            ));
        }

        $this->newLine();
        $this->info('Usa --usuario para ver el detalle o --analizar para ejecutar los detectores de trampas.');

        return self::SUCCESS;
    }

    private function detalle(int $usuarioId, ReputationService $reputacion): int
    {
        $usuario = $this->resolverUsuario($usuarioId);

        $this->line('=== Usuario #'.$usuario->id.' '.$usuario->email.' ===');
        $this->line('score: '.$usuario->reputation_score.' | saldo ledger: '.$reputacion->saldo($usuario));
        $this->newLine();

        $this->mostrarSanciones($usuario);
        $this->mostrarApelaciones($usuario);

        $entradas = $reputacion->historial($usuario);

        if ($entradas->isEmpty()) {
            $this->line('Ledger: sin asientos.');
        } else {
            $this->line('=== Ledger ('.count($entradas).') ===');

            foreach ($entradas as $entrada) {
                $this->line(sprintf(
                    '[%s] %+d %s %s',
                    $entrada->created_at?->toDateTimeString() ?? '?',
                    $entrada->puntos,
                    $entrada->motivo,
                    $entrada->reporte_id !== null ? '(reporte #'.$entrada->reporte_id.')' : '',
                ));
            }
        }

        return self::SUCCESS;
    }

    private function analizar(CheatDetectionService $cheat): int
    {
        $usuarioId = $this->option('usuario');

        if ($usuarioId !== null) {
            $this->analizarUsuario($cheat, $this->resolverUsuario((int) $usuarioId));

            return self::SUCCESS;
        }

        $usuarios = User::query()->orderBy('id')->get()->all();

        if ($usuarios === []) {
            $this->warn('No hay usuarios registrados.');

            return self::FAILURE;
        }

        foreach ($usuarios as $usuario) {
            $this->analizarUsuario($cheat, $usuario);
        }

        return self::SUCCESS;
    }

    private function analizarUsuario(CheatDetectionService $cheat, User $usuario): bool
    {
        $detecciones = $cheat->analizarUsuario($usuario);

        $this->line('=== Detectores de trampas — '.$usuario->email.' ===');

        if ($detecciones === []) {
            $this->line('Sin detecciones.');

            return false;
        }

        foreach ($detecciones as $deteccion) {
            $this->line(sprintf(
                '[%s] %s (%s)',
                $deteccion->gravedad->value,
                $deteccion->motivo,
                $deteccion->reporte_id !== null ? 'reporte #'.$deteccion->reporte_id : 'agregado',
            ));
            $this->line('    '.$deteccion->mensaje);
            $this->line('    metadata: '.json_encode($deteccion->metadata, JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();

        return true;
    }

    private function mostrarSanciones(User $usuario): void
    {
        $sanciones = $usuario->sanciones()->withCount('apelaciones')->orderByDesc('id')->get();

        if ($sanciones->isEmpty()) {
            $this->line('Sanciones: sin registros.');

            return;
        }

        $this->line('=== Sanciones ('.count($sanciones).') ===');

        foreach ($sanciones as $sancion) {
            $this->line(sprintf(
                '#%d %-32s %-7s %+d [%s]%s',
                $sancion->id,
                $sancion->motivo,
                $sancion->gravedad->value,
                $sancion->puntos,
                $sancion->estado->value,
                $sancion->reporte_id !== null ? ' (reporte #'.$sancion->reporte_id.')' : '',
            ));
        }

        $this->newLine();
    }

    private function mostrarApelaciones(User $usuario): void
    {
        $apelaciones = Apelacion::query()
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('id')
            ->get();

        if ($apelaciones->isEmpty()) {
            $this->line('Apelaciones: sin registros.');
            $this->newLine();

            return;
        }

        $this->line('=== Apelaciones ('.count($apelaciones).') ===');

        foreach ($apelaciones as $apelacion) {
            $this->line(sprintf(
                '#%d (sanción #%d) [%s]',
                $apelacion->id,
                $apelacion->sancion_id,
                $apelacion->estado->value,
            ));
        }

        $this->newLine();
    }

    private function resolverUsuario(int $id): User
    {
        /** @var User|null $usuario */
        $usuario = User::query()->find($id);

        if ($usuario === null) {
            throw new InvalidArgumentException("No existe el usuario [{$id}].");
        }

        return $usuario;
    }
}
