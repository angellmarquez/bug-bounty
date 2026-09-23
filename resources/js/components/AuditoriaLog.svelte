<script lang="ts">
    import ScrollText from '@lucide/svelte/icons/scroll-text';
    import type { Auditoria } from '@/types/domain';
    import EmptyState from '@/components/EmptyState.svelte';

    let {
        entries,
    }: {
        entries: { data: Auditoria[] };
    } = $props();

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

{#if entries.data.length === 0}
    <EmptyState
        icon={ScrollText}
        title="Sin registros"
        description="No hay entradas de auditoría."
    />
{:else}
    <div class="flex flex-col">
        {#each entries.data as entry (entry.id)}
            <div class="relative flex gap-4 pb-6">
                <div class="flex flex-col items-center">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-chart-5 text-white">
                        <ScrollText class="h-4 w-4" />
                    </div>
                    <div class="w-px flex-1 bg-border"></div>
                </div>
                <div class="flex-1 pt-1">
                    <p class="text-sm font-medium">{entry.accion}</p>
                    {#if entry.usuario}
                        <p class="text-xs text-muted-foreground">
                            por {entry.usuario.name}
                        </p>
                    {/if}
                    <p class="text-xs text-muted-foreground">
                        {entry.entidad_type} #{entry.entidad_id}
                    </p>
                    <p class="text-xs text-muted-foreground">{formatDate(entry.created_at)}</p>
                    {#if entry.detalle && Object.keys(entry.detalle).length > 0}
                        <pre class="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs text-muted-foreground">{JSON.stringify(entry.detalle, null, 2)}</pre>
                    {/if}
                </div>
            </div>
        {/each}
    </div>
{/if}
