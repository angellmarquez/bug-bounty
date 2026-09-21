<script lang="ts">
    import FilePlus from '@lucide/svelte/icons/file-plus';
    import Send from '@lucide/svelte/icons/send';
    import ArrowRightLeft from '@lucide/svelte/icons/arrow-right-left';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import Copy from '@lucide/svelte/icons/copy';
    import DollarSign from '@lucide/svelte/icons/dollar-sign';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import type { EventoReporte } from '@/types/domain';
    import { TipoEventoReporte } from '@/types/enums';
    import { tipoEventoLabel } from '@/lib/timeline-labels';
    import StateBadge from '@/components/StateBadge.svelte';
    import { estadoReporteDotColor } from '@/lib/status-colors';
    import type { EstadoReporte } from '@/types/enums';

    let { evento }: { evento: EventoReporte } = $props();

    const iconMap: Record<TipoEventoReporte, typeof FilePlus> = {
        [TipoEventoReporte.Creado]: FilePlus,
        [TipoEventoReporte.Enviado]: Send,
        [TipoEventoReporte.CambioDeEstado]: ArrowRightLeft,
        [TipoEventoReporte.Comentario]: MessageSquare,
        [TipoEventoReporte.MarcadoDuplicado]: Copy,
        [TipoEventoReporte.Pago]: DollarSign,
        [TipoEventoReporte.Sancion]: AlertTriangle,
        [TipoEventoReporte.Asignacion]: UserPlus,
    };

    const dotColorMap: Record<TipoEventoReporte, string> = {
        [TipoEventoReporte.Creado]: 'bg-chart-1',
        [TipoEventoReporte.Enviado]: 'bg-chart-2',
        [TipoEventoReporte.CambioDeEstado]: 'bg-chart-2',
        [TipoEventoReporte.Comentario]: 'bg-muted-foreground',
        [TipoEventoReporte.MarcadoDuplicado]: 'bg-chart-4',
        [TipoEventoReporte.Pago]: 'bg-chart-1',
        [TipoEventoReporte.Sancion]: 'bg-chart-3',
        [TipoEventoReporte.Asignacion]: 'bg-chart-5',
    };

    const Icon = $derived(iconMap[evento.tipo] ?? FilePlus);

    // El estado al que apunta el evento: en un cambio de estado, el nuevo estado; en el
    // resto, el que implica el tipo (p. ej. un pago deja el informe en "pagado").
    const estadoDelEvento = $derived.by((): EstadoReporte | null => {
        const nuevo = evento.metadata?.estado_nuevo;
        if (evento.tipo === TipoEventoReporte.CambioDeEstado && typeof nuevo === 'string') {
            return nuevo as EstadoReporte;
        }
        const porTipo: Partial<Record<TipoEventoReporte, EstadoReporte>> = {
            [TipoEventoReporte.Creado]: 'borrador',
            [TipoEventoReporte.Enviado]: 'enviado',
            [TipoEventoReporte.MarcadoDuplicado]: 'duplicado',
            [TipoEventoReporte.Pago]: 'pagado',
        };
        return porTipo[evento.tipo] ?? null;
    });

    const dotColor = $derived(
        estadoDelEvento
            ? estadoReporteDotColor(estadoDelEvento)
            : (dotColorMap[evento.tipo] ?? 'bg-muted-foreground'),
    );

    function formatDate(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }
</script>

<div class="relative flex gap-4 pb-6">
    <div class="flex flex-col items-center">
        <div class="flex h-8 w-8 items-center justify-center rounded-full {dotColor} text-white">
            <Icon class="h-4 w-4" />
        </div>
        <div class="w-px flex-1 bg-border"></div>
    </div>
    <div class="flex-1 pt-1">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-medium">{tipoEventoLabel(evento.tipo)}</p>
            {#if evento.tipo === TipoEventoReporte.CambioDeEstado && estadoDelEvento}
                <StateBadge estado={estadoDelEvento} />
            {/if}
        </div>
        {#if evento.actor}
            <p class="text-xs text-muted-foreground">
                por {evento.actor.name}
            </p>
        {/if}
        <p class="text-xs text-muted-foreground">{formatDate(evento.created_at)}</p>
        {#if evento.descripcion}
            <p class="mt-1 text-sm text-muted-foreground">{evento.descripcion}</p>
        {/if}
    </div>
</div>
