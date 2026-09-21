<script lang="ts">
    import Shield from '@lucide/svelte/icons/shield';
    import Button from '@/components/ui/button/Button.svelte';
    import Card from '@/components/ui/card/Card.svelte';
    import CardContent from '@/components/ui/card/CardContent.svelte';
    import CardHeader from '@/components/ui/card/CardHeader.svelte';
    import CardTitle from '@/components/ui/card/CardTitle.svelte';
    import type { Sancion } from '@/types/domain';
    import {
        gravedadSancionColor,
        gravedadSancionLabel,
        estadoSancionColor,
        estadoSancionLabel,
    } from '@/lib/status-colors';
    import EmptyState from '@/components/EmptyState.svelte';

    let {
        sanciones,
        showUsuario = false,
        showActions = false,
        onApelar,
        onRevocar,
    }: {
        sanciones: { data: Sancion[] };
        showUsuario?: boolean;
        showActions?: boolean;
        onApelar?: (sancionId: number) => void;
        onRevocar?: (sancionId: number) => void;
    } = $props();

    function formatDate(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(dateStr));
    }

    function puedeApelar(sancion: Sancion): boolean {
        if (sancion.puede_apelar !== undefined) return sancion.puede_apelar;
        if (sancion.estado !== 'aplicada') return false;
        if (!sancion.plazo_apelacion) return false;
        return new Date(sancion.plazo_apelacion) > new Date();
    }
</script>

{#if sanciones.data.length === 0}
    <EmptyState
        icon={Shield}
        title="Sin sanciones"
        description="No hay sanciones registradas."
    />
{:else}
    <div class="flex flex-col gap-4">
        {#each sanciones.data as sancion (sancion.id)}
            <Card>
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-4">
                        <CardTitle class="text-sm font-medium">
                            {sancion.motivo}
                        </CardTitle>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class={gravedadSancionColor(sancion.gravedad)}>
                                {gravedadSancionLabel(sancion.gravedad)}
                            </span>
                            <span class={estadoSancionColor(sancion.estado)}>
                                {estadoSancionLabel(sancion.estado)}
                            </span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                        <span>
                            Puntos: <span class="font-medium text-foreground">{sancion.puntos}</span>
                        </span>
                        <span>{formatDate(sancion.created_at)}</span>
                        {#if showUsuario && sancion.usuario}
                            <span>
                                Usuario: <span class="font-medium text-foreground">{sancion.usuario.name}</span>
                            </span>
                        {/if}
                        {#if sancion.suspension_hasta}
                            <span>
                                Suspensión hasta: <span class="font-medium text-foreground">{formatDate(sancion.suspension_hasta)}</span>
                            </span>
                        {/if}
                    </div>
                    {#if showActions}
                        <div class="mt-4 flex gap-2">
                            {#if onApelar && puedeApelar(sancion)}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() => onApelar(sancion.id)}
                                >
                                    Apelar
                                </Button>
                            {/if}
                            {#if onRevocar && sancion.estado !== 'revocada'}
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onclick={() => onRevocar(sancion.id)}
                                >
                                    Revocar
                                </Button>
                            {/if}
                        </div>
                    {/if}
                </CardContent>
            </Card>
        {/each}
    </div>
{/if}
