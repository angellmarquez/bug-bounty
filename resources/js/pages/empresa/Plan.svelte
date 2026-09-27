<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Mi plan', href: '/empresa/plan' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import Crown from '@lucide/svelte/icons/crown';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Loader from '@lucide/svelte/icons/loader-circle';
    import Wallet from '@lucide/svelte/icons/wallet';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import {
        ErrorPagoWallet,
        asegurarRed,
        conectar,
        direccionCorta,
        formatearUsdc,
        proveedor,
        saldoUsdc,
        transferirUsdc,
    } from '@/lib/wallet-usdc';
    import type { RedBounty } from '@/types/domain';

    type PagoPlan = {
        id: number;
        estado: 'verificando' | 'confirmado' | 'fallido';
        monto: number;
        dias: number;
        tx_hash: string;
        explorer_url: string | null;
        error: string | null;
        periodo_desde: string | null;
        periodo_hasta: string | null;
        created_at: string | null;
    };

    let {
        plan,
        pagos = [],
    }: {
        plan: {
            actual: 'comunitario' | 'profesional';
            expira_en: string | null;
            precio: number;
            dias: number;
            monto_unidades: string | null;
            tesoreria: string | null;
            red: RedBounty | null;
        };
        pagos?: PagoPlan[];
    } = $props();

    const BENEFICIOS = [
        'Programas privados: solo los investigadores que invites.',
        'Programas de élite: exigir rango Plata u Oro.',
        'Programas solo para investigadores verificados (filtro anti-spam).',
    ];

    const pendiente = $derived(pagos.find((p) => p.estado === 'verificando') ?? null);
    const esProfesional = $derived(plan.actual === 'profesional');

    let procesando = $state(false);
    let paso = $state('');
    let error = $state('');
    let hashManual = $state('');
    let verManual = $state(false);

    function fecha(iso: string | null): string {
        return iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' }).format(new Date(iso)) : '—';
    }

    function registrar(txHash: string, pagador: string | null) {
        paso = 'Registrando el pago para verificarlo…';
        procesando = true;
        router.post(
            '/empresa/plan/pagos',
            { tx_hash: txHash.trim(), pagador },
            {
                preserveScroll: true,
                onSuccess: () => {
                    hashManual = '';
                    verManual = false;
                },
                onError: (errores) => {
                    error = String(Object.values(errores)[0] ?? 'No se pudo registrar el pago.');
                },
                onFinish: () => {
                    procesando = false;
                    paso = '';
                },
            },
        );
    }

    async function pagarConWallet() {
        error = '';
        const eth = proveedor();
        const red = plan.red;

        if (!eth) {
            error = 'No se encontró una wallet en el navegador. Instala MetaMask o registra el hash de la transacción a mano.';
            return;
        }
        if (!red || !plan.tesoreria || !plan.monto_unidades) {
            error = 'El pago del plan no está disponible todavía.';
            return;
        }

        procesando = true;
        try {
            paso = 'Conectando con tu wallet…';
            const cuenta = await conectar(eth);

            paso = `Cambiando la wallet a ${red.nombre}…`;
            await asegurarRed(eth, red);

            paso = 'Revisando tu saldo de USDC…';
            const saldo = await saldoUsdc(eth, red, cuenta);
            if (saldo < BigInt(plan.monto_unidades)) {
                throw new ErrorPagoWallet(
                    `La cuenta conectada (${direccionCorta(cuenta)}) tiene ${formatearUsdc(saldo)} USDC y el plan cuesta ${plan.precio} USDC.` +
                        (red.testnet && red.faucets.usdc ? ` Consigue USDC de prueba en ${red.faucets.usdc}.` : ''),
                );
            }

            paso = 'Confirma el pago en tu wallet…';
            const txHash = await transferirUsdc(eth, red, cuenta, plan.tesoreria, plan.monto_unidades);

            registrar(txHash, cuenta);
        } catch (e) {
            error = e instanceof ErrorPagoWallet ? e.message : 'No se pudo completar el pago con la wallet.';
            procesando = false;
            paso = '';
        }
    }

    // Mientras un pago se verifica, la página vuelve a consultar la blockchain sola.
    $effect(() => {
        if (!pendiente) return;
        const id = pendiente.id;
        const intervalo = setInterval(() => {
            router.post(`/empresa/plan/pagos/${id}/comprobar`, {}, { preserveScroll: true, preserveState: true });
        }, 6000);

        return () => clearInterval(intervalo);
    });
</script>

<AppHead title="Mi plan" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <PageHeader title="Mi plan" description="El Plan Profesional se paga en USDC directamente a la wallet del proyecto y se verifica en la blockchain." />

    <div class="grid gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <CardTitle class="flex items-center gap-2"><Crown class="h-5 w-5 text-primary" /> Plan Profesional</CardTitle>
                    <Badge variant="outline" class={esProfesional ? 'border-primary/40 bg-primary/10 text-primary' : ''} data-test="plan-actual">
                        {esProfesional ? 'Activo' : 'Comunitario'}
                    </Badge>
                </div>
                <CardDescription>
                    {#if esProfesional}
                        Activo hasta el <strong>{fecha(plan.expira_en)}</strong>. Si renuevas antes, los días se suman.
                    {:else}
                        Tu empresa está en el Plan Comunitario.
                    {/if}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <p class="font-mono text-3xl font-bold">{plan.precio.toFixed(2)} <span class="text-sm text-muted-foreground">USDC / {plan.dias} días</span></p>
                <ul class="space-y-1.5">
                    {#each BENEFICIOS as beneficio (beneficio)}
                        <li class="flex items-start gap-2"><CheckCircle class="mt-0.5 h-4 w-4 shrink-0 text-primary" />{beneficio}</li>
                    {/each}
                </ul>
                <p class="text-xs text-muted-foreground">Al vencer, tus programas siguen funcionando; solo no podrás crear nuevos privados ni subir su nivel de exigencia.</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"><Wallet class="h-5 w-5 text-primary" /> {esProfesional ? 'Renovar' : 'Contratar'}</CardTitle>
                {#if plan.red}
                    <CardDescription>Pago en USDC sobre {plan.red.nombre}. La plataforma no guarda fondos ni claves: solo verifica el pago.</CardDescription>
                {/if}
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                {#if !plan.tesoreria}
                    <div class="flex items-start gap-2 rounded-md border border-aviso/40 bg-aviso/10 p-3 text-xs" role="alert" data-test="plan-sin-tesoreria">
                        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-aviso" />
                        El pago del plan todavía no está disponible: la administración debe configurar la wallet del proyecto.
                    </div>
                {:else if pendiente}
                    <div class="flex items-start gap-2 rounded-md border border-aviso/40 bg-aviso/10 p-3 text-xs" role="status" data-test="plan-verificando">
                        <Loader class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-aviso" />
                        <div>
                            <p class="font-medium">Verificando tu pago en la blockchain…</p>
                            <p class="text-muted-foreground">{pendiente.error ?? `Se necesitan ${plan.red?.confirmaciones ?? '?'} confirmaciones.`}</p>
                        </div>
                    </div>
                {:else}
                    <div class="space-y-1 text-xs">
                        <span class="text-muted-foreground">Wallet del proyecto (destino del pago)</span>
                        <p class="font-mono break-all" data-test="plan-tesoreria">{plan.tesoreria}</p>
                    </div>
                    <Button class="w-full" onclick={pagarConWallet} disabled={procesando} data-test="plan-pagar-wallet">
                        <Wallet class="mr-1.5 h-4 w-4" /> Pagar {plan.precio.toFixed(2)} USDC con mi wallet
                    </Button>
                    {#if plan.red?.testnet}
                        <p class="text-xs text-muted-foreground">
                            Red de pruebas: el USDC no tiene valor.
                            {#if plan.red.faucets.usdc}<a class="underline" href={plan.red.faucets.usdc} target="_blank" rel="noopener noreferrer">USDC de prueba</a>{/if}
                            {#if plan.red.faucets.gas} · <a class="underline" href={plan.red.faucets.gas} target="_blank" rel="noopener noreferrer">{plan.red.moneda_nativa.symbol} para el gas</a>{/if}
                        </p>
                    {/if}
                    <button type="button" class="text-xs text-muted-foreground underline" onclick={() => (verManual = !verManual)}>
                        ¿Pagaste desde otra wallet o un exchange? Registra el hash
                    </button>
                    {#if verManual}
                        <form class="space-y-2" onsubmit={(e) => { e.preventDefault(); registrar(hashManual, null); }}>
                            <Label for="plan-hash">Hash de la transacción</Label>
                            <Input id="plan-hash" bind:value={hashManual} placeholder="0x…" class="font-mono text-xs" required />
                            <Button type="submit" size="sm" variant="outline" disabled={procesando}>Registrar y verificar</Button>
                        </form>
                    {/if}
                {/if}

                {#if error}<p class="text-xs text-destructive" role="alert" data-test="plan-error">{error}</p>{/if}
                {#if paso}<p class="flex items-center gap-2 text-xs text-muted-foreground"><Spinner class="h-3 w-3" /> {paso}</p>{/if}
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader><CardTitle>Historial de pagos</CardTitle></CardHeader>
        <CardContent>
            {#if pagos.length === 0}
                <p class="text-sm text-muted-foreground">Todavía no hay pagos.</p>
            {:else}
                <ul class="divide-y divide-border text-sm">
                    {#each pagos as pago (pago.id)}
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" data-test="plan-pago">
                            <div class="space-y-0.5">
                                <p class="font-mono font-semibold">{pago.monto.toFixed(2)} USDC · {pago.dias} días</p>
                                <p class="text-xs text-muted-foreground">
                                    {fecha(pago.created_at)}
                                    {#if pago.estado === 'confirmado'} · hasta el {fecha(pago.periodo_hasta)}{/if}
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
            {/if}
        </CardContent>
    </Card>
</div>
