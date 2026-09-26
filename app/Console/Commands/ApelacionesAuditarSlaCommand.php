<?php

namespace App\Console\Commands;

use App\Services\Reputacion\ReputationService;
use Illuminate\Console\Command;

class ApelacionesAuditarSlaCommand extends Command
{
    protected $signature = 'apelaciones:auditar-sla';

    protected $description = 'Audita las apelaciones pendientes para alertar de retrasos en SLA y aplicar protecciones cautelares.';

    public function handle(ReputationService $reputacion): int
    {
        $this->info('Auditando SLA de apelaciones pendientes...');

        $resultado = $reputacion->auditarSlaApelaciones();

        $this->line('Apelaciones alertadas por retraso (> '.config('reputacion.sla_alerta_horas', 48)."h): {$resultado['alertadas']}");
        $this->line('Suspensiones protegidas provisionalmente (> '.config('reputacion.plazo_maximo_resolucion_dias', 5)." días): {$resultado['protegidas']}");

        $this->info('Auditoría de SLA completada exitosamente.');

        return self::SUCCESS;
    }
}
