<script lang="ts">
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import Button from '@/components/ui/button/Button.svelte';
    import Card from '@/components/ui/card/Card.svelte';
    import CardContent from '@/components/ui/card/CardContent.svelte';
    import CardHeader from '@/components/ui/card/CardHeader.svelte';
    import CardTitle from '@/components/ui/card/CardTitle.svelte';
    import type { Apelacion } from '@/types/domain';
    import {
        estadoApelacionColor,
        estadoApelacionLabel,
        estadoSancionColor,
        estadoSancionLabel,
    } from '@/lib/status-colors';
    import EmptyState from '@/components/EmptyState.svelte';

    let {
        apelaciones,
        showResolver = false,
        onResolver,
    }: {
        apelaciones: { data: Apelacion[] };
        showResolver?: boolean;
        onResolver?: (apelacionId: number, aprobada: boolean) => void;
    } = $props();

    function formatDate(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(dateStr));
    }
</script>

{#if apelaciones.data.length === 0}
    <EmptyState
        icon={MessageSquare}
        title="Sin apelaciones"
        description="No hay apelaciones registradas."
    />
{:else}
    <div class="flex flex-col gap-4">
        {#each apelaciones.data as apelacion (apelacion.id)}
            <Card>
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-4">
                        <CardTitle class="text-sm font-medium">
                            {apelacion.motivo}
                        </CardTitle>
                        <span class={estadoApelacionColor(apelacion.estado)}>
                            {estadoApelacionLabel(apelacion.estado)}
                        </span>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                        <span>{formatDate(apelacion.created_at)}</span>
                        {#if apelacion.sancion}
                            <span class="inline-flex items-center gap-1.5">
                                Sanción:
                                <span class={estadoSancionColor(apelacion.sancion.estado)}>
                                    {estadoSancionLabel(apelacion.sancion.estado)}
                                </span>
                            </span>
                        {/if}
                        {#if apelacion.resolucion}
                            <span>
                                Resolución: <span class="font-medium text-foreground">{apelacion.resolucion}</span>
                            </span>
                        {/if}
                    </div>
                    {#if showResolver && apelacion.estado === 'pendiente' && onResolver}
                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onclick={() => onResolver(apelacion.id, true)}
                            >
                                Aprobar
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onclick={() => onResolver(apelacion.id, false)}
                            >
                                Rechazar
                            </Button>
                        </div>
                    {/if}
                </CardContent>
            </Card>
        {/each}
    </div>
{/if}
