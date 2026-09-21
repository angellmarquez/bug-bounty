<script lang="ts">
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import VistaRapidaInforme from '@/components/VistaRapidaInforme.svelte';
    import { Button } from '@/components/ui/button';
    import type { ReporteCompacto } from '@/types/domain';

    let {
        reportes,
        mostrarPrograma = true,
        vistaRapida = false,
        etiquetaAccion = () => 'Ver informe',
        destacar = () => false,
    }: {
        reportes: ReporteCompacto[];
        mostrarPrograma?: boolean;
        /** Permite leer el informe dentro de la propia lista, sin abrir su página. */
        vistaRapida?: boolean;
        etiquetaAccion?: (reporte: ReporteCompacto) => string;
        destacar?: (reporte: ReporteCompacto) => boolean;
    } = $props();

    let abierto = $state<number | null>(null);

    const columnas = $derived(
        vistaRapida
            ? 'md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)_6rem_8.5rem_5.5rem_13.5rem]'
            : 'md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)_6rem_8.5rem_5.5rem_7.5rem]',
    );

    function formatearFecha(fecha: string | null): string {
        if (!fecha) return 'Sin enviar';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: '2-digit',
        }).format(new Date(fecha));
    }
</script>

<div class="overflow-hidden rounded-md border">
    <div
        class="hidden gap-3 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground md:grid {columnas}"
    >
        <span>Informe</span>
        <span>Investigador</span>
        <span>Severidad</span>
        <span>Estado</span>
        <span>Enviado</span>
        <span></span>
    </div>

    {#each reportes as reporte (reporte.id)}
        <div class="border-b last:border-b-0">
            <div class="grid gap-2 px-3 py-3 md:items-center md:gap-3 {columnas}">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium" title={reporte.titulo}>
                        {reporte.numero_reporte} · {reporte.titulo}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {#if mostrarPrograma}{reporte.programa_nombre ?? ''}{/if}
                        {#if reporte.asignado_a}{mostrarPrograma ? ' · ' : ''}Asignado a {reporte.asignado_a.name}{/if}
                        {#if reporte.es_duplicado_de}{' · '}Duplicado del informe #{reporte.es_duplicado_de}{/if}
                    </p>
                </div>

                <div class="min-w-0">
                    <p class="truncate text-sm">{reporte.investigador.name}</p>
                    <p class="text-xs text-muted-foreground">
                        <RangoBadge puntos={reporte.investigador.reputation_score} class="align-middle" />
                        {reporte.investigador.reputation_score} pts
                        {#if (reporte.investigador.reportes_descartados ?? 0) > 0}
                            · <span class="text-chart-4">{reporte.investigador.reportes_descartados} descartados</span>
                        {/if}
                    </p>
                </div>

                <div>
                    {#if reporte.severidad}
                        <SeverityBadge severidad={reporte.severidad} />
                    {:else}
                        <span class="text-xs text-muted-foreground">Sin severidad</span>
                    {/if}
                </div>

                <div><StateBadge estado={reporte.estado} /></div>

                <p class="text-xs text-muted-foreground">{formatearFecha(reporte.enviado_en)}</p>

                <div class="flex flex-wrap gap-1 md:justify-end">
                    {#if vistaRapida}
                        <Button
                            size="sm"
                            variant="ghost"
                            aria-expanded={abierto === reporte.id}
                            onclick={() => (abierto = abierto === reporte.id ? null : reporte.id)}
                        >
                            {abierto === reporte.id ? 'Ocultar' : 'Vista rápida'}
                        </Button>
                    {/if}
                    <Button
                        size="sm"
                        variant={destacar(reporte) ? 'default' : 'outline'}
                        href={`/reportes/${reporte.id}`}
                    >
                        {etiquetaAccion(reporte)}
                    </Button>
                </div>
            </div>

            {#if vistaRapida && abierto === reporte.id}
                <div class="border-t bg-muted/20 px-3 py-4">
                    <VistaRapidaInforme reporteId={reporte.id} />
                </div>
            {/if}
        </div>
    {/each}
</div>
