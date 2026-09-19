<script lang="ts">
    import type { EventoReporte } from '@/types/domain';
    import TimelineEvent from '@/components/TimelineEvent.svelte';
    import EmptyState from '@/components/EmptyState.svelte';

    let { eventos = [] }: { eventos?: EventoReporte[] } = $props();
</script>

{#if eventos.length === 0}
    <EmptyState
        title="Sin eventos"
        description="No hay eventos registrados en la línea de tiempo de este reporte."
    />
{:else}
    <div class="space-y-0">
        {#each eventos as evento (evento.id)}
            <TimelineEvent {evento} />
        {/each}
    </div>
{/if}
