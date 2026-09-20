<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Bug from '@lucide/svelte/icons/bug';
    import EmptyState from '@/components/EmptyState.svelte';
    import {
        Tooltip,
        TooltipContent,
        TooltipProvider,
        TooltipTrigger,
    } from '@/components/ui/tooltip';
    import {
        estadoReporteDotColor,
        estadoReporteEnProgreso,
        estadoReporteLabel,
    } from '@/lib/status-colors';
    import { show as reportesShow } from '@/routes/reportes';
    import type { EstadoReporte } from '@/types/enums';

    type ReporteTimelineItem = {
        id: number;
        numero_reporte: string;
        titulo: string;
        estado: EstadoReporte;
        fecha: string;
        programa?: { nombre: string } | null;
    };

    let {
        reportes = [],
    }: {
        reportes?: ReporteTimelineItem[];
    } = $props();

    const ordenados = $derived(
        [...reportes].sort(
            (a, b) => new Date(a.fecha).getTime() - new Date(b.fecha).getTime(),
        ),
    );

    const estadosPresentes = $derived(
        [...new Set(ordenados.map((r) => r.estado))] as EstadoReporte[],
    );

    function formatearFecha(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
        }).format(new Date(dateStr));
    }

    function formatearFechaCompleta(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }
</script>

{#if ordenados.length === 0}
    <EmptyState
        icon={Bug}
        title="Aún no hay reportes"
        description="Cuando envíes tu primer reporte, aparecerá aquí en tu línea de tiempo."
    />
{:else}
    <TooltipProvider delayDuration={150}>
        <div class="space-y-4">
            <div class="overflow-x-auto pb-2">
                <div class="flex min-w-max items-start px-2">
                    {#each ordenados as reporte, i (reporte.id)}
                        <Tooltip>
                            <TooltipTrigger>
                                {#snippet child({ props })}
                                    <Link
                                        href={reportesShow(reporte.id)}
                                        {...props}
                                        class="group flex w-24 shrink-0 flex-col items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <span
                                            class="relative flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-background transition-transform duration-150 group-hover:scale-125 {estadoReporteDotColor(
                                                reporte.estado,
                                            )}"
                                        >
                                            {#if estadoReporteEnProgreso(reporte.estado)}
                                                <span
                                                    class="absolute inset-0 rounded-full {estadoReporteDotColor(
                                                        reporte.estado,
                                                    )} animate-ping opacity-60"
                                                ></span>
                                            {/if}
                                        </span>
                                        <span
                                            class="text-center text-xs text-muted-foreground group-hover:text-foreground"
                                        >
                                            {formatearFecha(reporte.fecha)}
                                        </span>
                                    </Link>
                                {/snippet}
                            </TooltipTrigger>
                            <TooltipContent side="top" sideOffset={10} class="max-w-56 shadow-lg">
                                <div class="space-y-1 text-left">
                                    <p class="text-xs font-semibold">
                                        {reporte.numero_reporte} · {reporte.titulo}
                                    </p>
                                    <p class="text-xs">
                                        Estado: {estadoReporteLabel(reporte.estado)}
                                    </p>
                                    {#if reporte.programa}
                                        <p class="text-xs text-muted-foreground">
                                            Programa: {reporte.programa.nombre}
                                        </p>
                                    {/if}
                                    <p class="text-xs text-muted-foreground">
                                        {formatearFechaCompleta(reporte.fecha)}
                                    </p>
                                </div>
                            </TooltipContent>
                        </Tooltip>

                        {#if i < ordenados.length - 1}
                            <div class="flex h-6 w-10 shrink-0 items-center sm:w-14">
                                <div class="h-0.5 w-full bg-border"></div>
                            </div>
                        {/if}
                    {/each}
                </div>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-2 border-t border-border pt-3">
                {#each estadosPresentes as estado (estado)}
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-2.5 w-2.5 rounded-full {estadoReporteDotColor(
                                estado,
                            )}"
                        ></span>
                        <span class="text-xs text-muted-foreground">
                            {estadoReporteLabel(estado)}
                        </span>
                    </div>
                {/each}
            </div>
        </div>
    </TooltipProvider>
{/if}
