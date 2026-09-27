<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Ingresos', href: '/admin/ingresos' }],
    };
</script>

<script lang="ts">
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Receipt from '@lucide/svelte/icons/receipt';
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { direccionCorta } from '@/lib/wallet-usdc';

    type Pago = {
        id: number;
        empresa: string;
        estado: 'verificando' | 'confirmado' | 'fallido';
        monto: number;
        tx_hash: string;
        explorer_url: string | null;
        periodo_hasta: string | null;
        error: string | null;
        created_at: string | null;
    };

    let {
        resumen,
        pagos = [],
    }: {
        resumen: {
            total_usdc: number;
            pagos_confirmados: number;
            ultimos_30_dias_usdc: number;
            empresas_profesional: number;
            tesoreria: string | null;
            explorer_tesoreria: string | null;
            red: string | null;
        };
        pagos?: Pago[];
    } = $props();

    function fecha(iso: string | null): string {
        return iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(new Date(iso)) : '—';
    }
</script>

<AppHead title="Ingresos" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <PageHeader title="Ingresos" description={`Pagos del Plan Profesional verificados en la blockchain${resumen.red ? ` (${resumen.red})` : ''}.`} />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {#each [
            { titulo: 'Total recibido', valor: `${resumen.total_usdc.toFixed(2)} USDC` },
            { titulo: 'Últimos 30 días', valor: `${resumen.ultimos_30_dias_usdc.toFixed(2)} USDC` },
            { titulo: 'Pagos confirmados', valor: String(resumen.pagos_confirmados) },
            { titulo: 'Empresas con Plan Profesional', valor: String(resumen.empresas_profesional) },
        ] as tarjeta (tarjeta.titulo)}
            <Card>
                <CardHeader class="pb-1"><CardTitle class="text-xs font-medium text-muted-foreground">{tarjeta.titulo}</CardTitle></CardHeader>
                <CardContent><p class="font-mono text-2xl font-bold" data-test="ingresos-resumen">{tarjeta.valor}</p></CardContent>
            </Card>
        {/each}
    </div>

    <p class="text-xs text-muted-foreground">
        {#if resumen.tesoreria}
            Tesorería: <span class="font-mono">{resumen.tesoreria}</span>
            {#if resumen.explorer_tesoreria}
                · <a href={resumen.explorer_tesoreria} target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">ver en el explorador</a>
            {/if}
        {:else}
            No hay wallet de tesorería configurada: las empresas no pueden pagar el plan. Configúrala en <a class="underline" href="/admin/config/plan">Config. plan</a>.
        {/if}
    </p>

    {#if pagos.length === 0}
        <EmptyState icon={Receipt} title="Sin pagos todavía" description="Cuando una empresa pague el Plan Profesional aparecerá aquí." />
    {:else}
        <Card>
            <CardContent class="pt-6">
                <ul class="divide-y divide-border text-sm">
                    {#each pagos as pago (pago.id)}
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" data-test="ingreso">
                            <div class="space-y-0.5">
                                <p class="font-medium">{pago.empresa}</p>
                                <p class="text-xs text-muted-foreground">
                                    {fecha(pago.created_at)} · <span class="font-mono">{pago.monto.toFixed(2)} USDC</span>
                                    {#if pago.estado === 'confirmado' && pago.periodo_hasta} · plan hasta el {fecha(pago.periodo_hasta)}{/if}
                                    {#if pago.estado === 'fallido' && pago.error} · {pago.error}{/if}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                {#if pago.explorer_url}
                                    <a href={pago.explorer_url} target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 font-mono text-xs text-primary hover:underline">
                                        {direccionCorta(pago.tx_hash)} <ExternalLink class="h-3 w-3" />
                                    </a>
                                {/if}
                                <Badge
                                    variant="outline"
                                    class={pago.estado === 'confirmado'
                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                        : pago.estado === 'fallido'
                                          ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                          : 'border-aviso/40 bg-aviso/10 text-aviso'}
                                >
                                    {pago.estado === 'confirmado' ? 'Confirmado' : pago.estado === 'fallido' ? 'No válido' : 'Verificando'}
                                </Badge>
                            </div>
                        </li>
                    {/each}
                </ul>
            </CardContent>
        </Card>
    {/if}
</div>
