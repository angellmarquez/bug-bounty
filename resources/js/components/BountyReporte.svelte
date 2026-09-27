<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Coins from '@lucide/svelte/icons/coins';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import Wallet from '@lucide/svelte/icons/wallet';
    import Loader from '@lucide/svelte/icons/loader-circle';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
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
    import type { BountyInforme, EstadoBounty } from '@/types/domain';

    let {
        reporteId,
        bounty,
        puedeAsignar = false,
        puedePagar = false,
        esInvestigador = false,
        severidad = null,
    }: {
        reporteId: number;
        bounty: BountyInforme;
        puedeAsignar?: boolean;
        puedePagar?: boolean;
        esInvestigador?: boolean;
        severidad?: string | null;
    } = $props();

    const ETIQUETAS: Record<EstadoBounty, string> = {
        sin_bounty: 'Sin asignar',
        asignado: 'Asignado',
        verificando: 'Verificando',
        pagado: 'Pagado',
        fallido: 'Pago no válido',
    };

    const CLASES_ESTADO: Record<EstadoBounty, string> = {
        sin_bounty: 'border-border text-muted-foreground',
        asignado: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
        verificando: 'border-aviso/40 bg-aviso/10 text-aviso',
        pagado: 'border-primary/40 bg-primary/10 text-primary',
        fallido: 'border-destructive/40 bg-destructive/10 text-destructive',
    };

    const sugerido = $derived.by((): string => {
        const valor = severidad ? bounty.tabla_recompensas?.[severidad.toLowerCase()] : undefined;

        return valor ? String(valor) : bounty.recompensa_min ? String(bounty.recompensa_min) : '';
    });

    let editandoMonto = $state(false);
    let monto = $state('');
    let procesando = $state(false);
    let error = $state('');
    // Paso del pago con wallet, para explicar qué está pasando.
    let paso = $state('');
    let hashManual = $state('');
    let verManual = $state(false);

    function abrirEdicion() {
        monto = bounty.monto ? String(bounty.monto) : sugerido;
        error = '';
        editandoMonto = true;
    }

    function enviar(url: string, datos: Record<string, string | number | null>, alTerminar?: () => void) {
        procesando = true;
        error = '';
        router.post(url, datos, {
            preserveScroll: true,
            onSuccess: () => alTerminar?.(),
            onError: (errores) => {
                error = String(Object.values(errores)[0] ?? 'No se pudo completar la acción.');
            },
            onFinish: () => {
                procesando = false;
                paso = '';
            },
        });
    }

    function asignar() {
        enviar(`/reportes/${reporteId}/bounty/asignar`, { monto: Number(monto) }, () => (editandoMonto = false));
    }

    function registrarHash(txHash: string, pagador: string | null) {
        paso = 'Registrando la transacción para verificarla…';
        enviar(`/reportes/${reporteId}/bounty/transaccion`, { tx_hash: txHash.trim(), pagador }, () => {
            hashManual = '';
            verManual = false;
        });
    }

    async function pagarConWallet() {
        error = '';
        const eth = proveedor();
        const red = bounty.red;

        if (!eth) {
            error = 'No se encontró una wallet en el navegador. Instala MetaMask o registra el hash de la transacción a mano.';
            return;
        }
        if (!red || !bounty.wallet_destino || !bounty.monto_unidades) {
            error = 'Faltan datos para pagar (red, wallet del investigador o monto).';
            return;
        }

        procesando = true;
        try {
            paso = 'Conectando con tu wallet…';
            const cuenta = await conectar(eth);
            // MetaMask recuerda qué cuenta está conectada a cada web, aunque en la extensión se vea otra.
            if (cuenta.toLowerCase() === bounty.wallet_destino.toLowerCase()) {
                throw new ErrorPagoWallet(
                    `La wallet conectada a esta página (${direccionCorta(cuenta)}) es la del investigador. En MetaMask conecta la cuenta de la empresa a este sitio y vuelve a intentarlo.`,
                );
            }

            paso = `Cambiando la wallet a ${red.nombre}…`;
            await asegurarRed(eth, red);

            paso = 'Revisando tu saldo de USDC…';
            const saldo = await saldoUsdc(eth, red, cuenta);
            if (saldo < BigInt(bounty.monto_unidades)) {
                throw new ErrorPagoWallet(
                    `La cuenta conectada a esta página (${direccionCorta(cuenta)}) tiene ${formatearUsdc(saldo)} USDC y el bounty es de ${bounty.monto} USDC. ` +
                        'Si tus fondos están en otra cuenta, conéctala a este sitio en MetaMask.' +
                        (red.testnet && red.faucets.usdc ? ` Consigue USDC de prueba en ${red.faucets.usdc}.` : ''),
                );
            }

            paso = 'Confirma la transferencia en tu wallet…';
            const txHash = await transferirUsdc(eth, red, cuenta, bounty.wallet_destino, bounty.monto_unidades);

            registrarHash(txHash, cuenta);
        } catch (e) {
            error = e instanceof ErrorPagoWallet ? e.message : 'No se pudo completar el pago con la wallet.';
            procesando = false;
            paso = '';
        }
    }

    // Mientras se verifica, la página vuelve a consultar la blockchain sola.
    $effect(() => {
        if (bounty.estado !== 'verificando') return;

        const intervalo = setInterval(() => {
            router.post(`/reportes/${reporteId}/bounty/comprobar`, {}, { preserveScroll: true, preserveState: true });
        }, 6000);

        return () => clearInterval(intervalo);
    });

    function corto(texto: string | null): string {
        return texto ? `${texto.slice(0, 8)}…${texto.slice(-6)}` : '';
    }
</script>

<div data-test="bounty"><Card>
    <CardHeader class="pb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="rounded-md bg-primary/10 p-1.5 text-primary">
                    <Coins class="h-4 w-4" />
                </div>
                <CardTitle class="text-sm font-semibold">Recompensa (bounty)</CardTitle>
            </div>
            <Badge variant="outline" class={CLASES_ESTADO[bounty.estado]} data-test="bounty-estado">{ETIQUETAS[bounty.estado]}</Badge>
        </div>
        {#if bounty.red}
            <CardDescription class="text-xs">
                Pago en USDC sobre {bounty.red.nombre}. La plataforma no custodia fondos: verifica el pago en la blockchain.
            </CardDescription>
        {/if}
    </CardHeader>

    <CardContent class="space-y-4 text-sm">
        {#if bounty.monto}
            <p class="font-mono text-2xl font-bold" data-test="bounty-monto">{bounty.monto.toFixed(2)} <span class="text-sm text-muted-foreground">USDC</span></p>
        {:else}
            <p class="text-muted-foreground">
                {bounty.recompensa_min || bounty.recompensa_max
                    ? `El programa paga entre ${bounty.recompensa_min ?? 0} y ${bounty.recompensa_max ?? '—'} USDC.`
                    : 'La empresa aún no asignó una recompensa a este informe.'}
            </p>
        {/if}

        {#if esInvestigador && !bounty.tiene_wallet && bounty.estado !== 'pagado'}
            <div class="rounded-md border border-aviso/40 bg-aviso/10 p-3 text-xs" role="alert">
                Para cobrar necesitas una wallet en tu perfil.
                <Link href="/settings/profile" class="font-medium underline">Configurarla</Link>
            </div>
        {/if}

        {#if bounty.estado === 'verificando'}
            <div class="flex items-start gap-2 rounded-md border border-aviso/40 bg-aviso/10 p-3 text-xs" role="status" data-test="bounty-verificando">
                <Loader class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-aviso" />
                <div class="space-y-1">
                    <p class="font-medium">Verificando el pago en la blockchain…</p>
                    <p class="text-muted-foreground">{bounty.error ?? `Se necesitan ${bounty.red?.confirmaciones ?? '?'} confirmaciones.`}</p>
                </div>
            </div>
        {/if}

        {#if bounty.estado === 'fallido' && bounty.error}
            <div class="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-xs" role="alert" data-test="bounty-error">
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                <p>{bounty.error}</p>
            </div>
        {/if}

        {#if bounty.estado === 'pagado'}
            <div class="flex items-start gap-2 rounded-md border border-primary/40 bg-primary/10 p-3 text-xs" data-test="bounty-pagado">
                <CheckCircle class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div class="space-y-1">
                    <p class="font-medium">Pago verificado en la blockchain</p>
                    {#if bounty.pagado_en}
                        <p class="text-muted-foreground">{new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(bounty.pagado_en))}{bounty.bloque ? ` · bloque ${bounty.bloque}` : ''}</p>
                    {/if}
                </div>
            </div>
        {/if}

        {#if bounty.tx_hash}
            <div class="space-y-1 text-xs">
                <span class="text-muted-foreground">Transacción</span>
                {#if bounty.explorer_url}
                    <a href={bounty.explorer_url} target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 font-mono text-primary hover:underline" data-test="bounty-explorer">
                        {corto(bounty.tx_hash)}
                        <ExternalLink class="h-3 w-3" />
                    </a>
                {:else}
                    <span class="font-mono">{corto(bounty.tx_hash)}</span>
                {/if}
            </div>
        {/if}

        {#if bounty.wallet_destino && bounty.estado !== 'pagado'}
            <div class="space-y-1 text-xs">
                <span class="text-muted-foreground">Wallet del investigador</span>
                <p class="font-mono break-all">{bounty.wallet_destino}</p>
            </div>
        {/if}

        {#if error}
            <p class="text-xs text-destructive" role="alert" data-test="bounty-error-accion">{error}</p>
        {/if}
        {#if paso}
            <p class="flex items-center gap-2 text-xs text-muted-foreground"><Spinner class="h-3 w-3" /> {paso}</p>
        {/if}

        {#if puedeAsignar && editandoMonto}
            <form class="space-y-2" onsubmit={(e) => { e.preventDefault(); asignar(); }}>
                <Label for="bounty-monto">Monto en USDC</Label>
                <Input id="bounty-monto" type="number" step="0.01" min={bounty.recompensa_min ?? 1} max={bounty.recompensa_max ?? undefined} bind:value={monto} required />
                <p class="text-xs text-muted-foreground">
                    {sugerido ? `Sugerido por la tabla del programa: ${sugerido} USDC.` : ''}
                    {bounty.recompensa_min || bounty.recompensa_max ? ` Rango del programa: ${bounty.recompensa_min ?? 1}–${bounty.recompensa_max ?? '∞'} USDC.` : ''}
                </p>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" disabled={procesando}>{#if procesando}<Spinner class="mr-1 h-3 w-3" />{/if}Guardar monto</Button>
                    <Button type="button" size="sm" variant="ghost" onclick={() => (editandoMonto = false)}>Cancelar</Button>
                </div>
            </form>
        {:else if puedeAsignar}
            <Button size="sm" variant="outline" class="w-full" onclick={abrirEdicion} data-test="bounty-asignar">
                {bounty.monto ? 'Cambiar monto' : 'Asignar recompensa'}
            </Button>
        {/if}

        {#if puedePagar && !editandoMonto}
            <div class="space-y-2 border-t border-border pt-3">
                {#if !bounty.wallet_destino}
                    <p class="text-xs text-muted-foreground">El investigador aún no configuró su wallet: no se puede pagar todavía.</p>
                {:else}
                    <Button size="sm" class="w-full" onclick={pagarConWallet} disabled={procesando} data-test="bounty-pagar-wallet">
                        <Wallet class="mr-1.5 h-4 w-4" />
                        Pagar {bounty.monto?.toFixed(2)} USDC con mi wallet
                    </Button>
                    {#if bounty.red?.testnet}
                        <p class="text-xs text-muted-foreground">
                            Red de pruebas: el USDC no tiene valor.
                            {#if bounty.red.faucets.usdc}<a class="underline" href={bounty.red.faucets.usdc} target="_blank" rel="noopener noreferrer">USDC de prueba</a>{/if}
                            {#if bounty.red.faucets.gas} · <a class="underline" href={bounty.red.faucets.gas} target="_blank" rel="noopener noreferrer">{bounty.red.moneda_nativa.symbol} para el gas</a>{/if}
                        </p>
                    {/if}

                    <button type="button" class="text-xs text-muted-foreground underline" onclick={() => (verManual = !verManual)}>
                        ¿Pagaste desde otra wallet o un exchange? Registra el hash
                    </button>
                    {#if verManual}
                        <form class="space-y-2" onsubmit={(e) => { e.preventDefault(); registrarHash(hashManual, null); }}>
                            <Label for="bounty-hash">Hash de la transacción</Label>
                            <Input id="bounty-hash" bind:value={hashManual} placeholder="0x…" class="font-mono text-xs" required />
                            <Button type="submit" size="sm" variant="outline" disabled={procesando}>Registrar y verificar</Button>
                        </form>
                    {/if}
                {/if}
            </div>
        {/if}
    </CardContent>
</Card></div>
