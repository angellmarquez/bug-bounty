<script lang="ts">
    import Check from '@lucide/svelte/icons/check';
    import X from '@lucide/svelte/icons/x';
    import { estadoReporteLabel } from '@/lib/status-colors';
    import type { EstadoReporte } from '@/types/enums';

    let { estado }: { estado: EstadoReporte } = $props();

    const CAMINO: EstadoReporte[] = [
        'enviado',
        'en_revision',
        'validado',
        'en_reparacion',
        'pago_pendiente',
        'pagado',
        'cerrado',
    ];
    const DESCARTADOS: EstadoReporte[] = ['rechazado', 'duplicado', 'fuera_de_alcance'];

    const descartado = $derived(DESCARTADOS.includes(estado));

    // Un informe descartado recorrió enviado y en revisión antes de terminar.
    const pasos = $derived<EstadoReporte[]>(
        descartado ? ['enviado', 'en_revision', estado] : CAMINO,
    );

    const indiceActual = $derived(
        descartado ? pasos.length - 1 : CAMINO.indexOf(estado),
    );
</script>

{#if estado === 'borrador'}
    <p class="text-xs text-muted-foreground">
        Borrador: todavía no lo enviaste al programa. Envíalo para que un moderador lo revise.
    </p>
{:else}
    <ol class="flex items-start gap-0 overflow-x-auto pb-1" aria-label="Avance del informe">
        {#each pasos as paso, i (paso)}
            {@const completado = i < indiceActual}
            {@const actual = i === indiceActual}
            {@const fallido = descartado && actual}
            <li class="flex min-w-20 flex-1 flex-col items-center gap-1">
                <div class="flex w-full items-center">
                    <div class="h-px flex-1 {i === 0 ? 'bg-transparent' : completado || actual ? 'bg-chart-1' : 'bg-border'}"></div>
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold
                        {fallido
                            ? 'bg-destructive text-destructive-foreground'
                            : completado
                              ? 'bg-chart-1 text-primary-foreground'
                              : actual
                                ? 'bg-primary text-primary-foreground ring-2 ring-primary/40'
                                : 'bg-muted text-muted-foreground'}"
                        aria-current={actual ? 'step' : undefined}
                    >
                        {#if fallido}
                            <X class="h-3 w-3" />
                        {:else if completado}
                            <Check class="h-3 w-3" />
                        {:else}
                            {i + 1}
                        {/if}
                    </span>
                    <div class="h-px flex-1 {i === pasos.length - 1 ? 'bg-transparent' : completado ? 'bg-chart-1' : 'bg-border'}"></div>
                </div>
                <span class="text-center text-[11px] leading-tight {actual ? 'font-medium text-foreground' : 'text-muted-foreground'}">
                    {estadoReporteLabel(paso)}
                </span>
            </li>
        {/each}
    </ol>
{/if}
