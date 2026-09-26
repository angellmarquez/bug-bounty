<?php

namespace App\Services\Moderacion;

use App\Enums\EstadoPrograma;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\User;
use App\Services\Notificaciones\Notificador;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicio de balanceo de carga y asignación automática de moderadores a programas.
 *
 * Asigna automáticamente moderadores activos con menor carga de trabajo a cada programa activo,
 * respetando el límite máximo de programas simultáneos y las reglas de seguridad anti-conflicto.
 * Si todos los moderadores alcanzan su límite, el sistema notifica al administrador y pone el
 * programa en cola hasta que un moderador se libere o se asigne manualmente.
 */
class AutoAsignadorModeradores
{
    public function __construct(
        protected Notificador $notificador,
    ) {}

    public function limitePorModerador(): int
    {
        return (int) config('moderacion.limite_programas_por_moderador', 5);
    }

    public function moderadoresPorPrograma(): int
    {
        return (int) config('moderacion.moderadores_por_programa', 2);
    }

    /**
     * Cantidad de programas actualmente activos que modera un usuario.
     */
    public function programasActivosDe(User $moderador): int
    {
        return $moderador->programasModerados()
            ->where('estado', EstadoPrograma::Activo)
            ->count();
    }

    /**
     * Asigna automáticamente los moderadores con menor carga disponibles a un programa activo.
     *
     * @return int Cantidad de moderadores que fueron asignados en esta ejecución.
     */
    public function asignarAPrograma(Programa $programa, bool $notificarSiLiberado = false): int
    {
        if ($programa->estado !== EstadoPrograma::Activo) {
            return 0;
        }

        $deseados = $this->moderadoresPorPrograma();
        $limite = $this->limitePorModerador();
        $actuales = $programa->moderadores()->count();

        if ($actuales >= $deseados) {
            return 0;
        }

        $faltantes = $deseados - $actuales;

        // Búsqueda de moderadores elegibles y disponibles bajo el límite de carga
        $elegibles = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', 'moderador'))
            ->where('is_active', true)
            // Sin suspension activa
            ->whereDoesntHave('sanciones', fn (Builder $q) => $q->suspensionEnCurso())
            // Sin vinculacion empresarial activa (garantia de no conflicto)
            ->whereDoesntHave('empresas', fn (Builder $q) => $q->where('empresa_usuario.estado', 'activo'))
            // Sin reportes previos en este programa como investigador (anti-conflicto de interes)
            ->whereDoesntHave('reportes', fn (Builder $q) => $q->where('programa_id', $programa->id))
            // Que no esté ya asignado a este programa
            ->whereDoesntHave('programasModerados', fn (Builder $q) => $q->whereKey($programa->id))
            // Conteo de programas activos para balancear carga
            ->withCount(['programasModerados as programas_activos_count' => fn (Builder $q) => $q->where('estado', EstadoPrograma::Activo)])
            ->orderBy('programas_activos_count', 'asc')
            ->get()
            ->filter(fn (User $user) => (int) $user->programas_activos_count < $limite)
            ->take($faltantes);

        if ($elegibles->isEmpty()) {
            // Si el programa no tiene ningún moderador asignado y no hay nadie libre, avisar al admin
            if ($actuales === 0) {
                $this->notificador->moderadoresCapacidadAlcanzada($programa);
            }

            return 0;
        }

        $asignados = 0;
        foreach ($elegibles as $moderador) {
            $programa->moderadores()->syncWithoutDetaching([
                $moderador->id => ['asignado_por' => null],
            ]);

            Auditoria::registrar(
                'admin.moderador.programa.auto_asignado',
                $moderador,
                ['programa' => $programa->nombre, 'programa_id' => $programa->id],
            );

            $this->notificador->moderadorPrograma($moderador, $programa, true);

            if ($notificarSiLiberado) {
                $this->notificador->programaAutoAsignadoTrasLiberarse($programa, $moderador);
            }

            $asignados++;
        }

        return $asignados;
    }

    /**
     * Revisa programas activos que carecen de moderadores suficientes e intenta auto-asignarles
     * moderadores que ahora tengan cupo libre.
     *
     * @return int Cantidad total de asignaciones realizadas.
     */
    public function atenderProgramasPendientes(): int
    {
        $deseados = $this->moderadoresPorPrograma();

        $programasIncompletos = Programa::query()
            ->where('estado', EstadoPrograma::Activo)
            ->withCount('moderadores')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn (Programa $p) => (int) $p->moderadores_count < $deseados);

        $totalAsignados = 0;
        foreach ($programasIncompletos as $programa) {
            $totalAsignados += $this->asignarAPrograma($programa, notificarSiLiberado: true);
        }

        return $totalAsignados;
    }
}
