<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Coins from '@lucide/svelte/icons/coins';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Copy from '@lucide/svelte/icons/copy';
    import Check from '@lucide/svelte/icons/check';
    import AlertCircle from '@lucide/svelte/icons/alert-circle';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Spinner } from '@/components/ui/spinner';

    interface BountyProps {
        monto: number | null;
        moneda: string;
        estado: 'sin_asignar' | 'asignado' | 'pagado';
        tx_hash: string | null;
        red: string | null;
        pagado_en: string | null;
        explorer_url: string | null;
        investigador_wallet: string | null;
        investigador_wallet_red: string | null;
        programa_tiene_recompensas: boolean;
        tabla_recompensas?: Record<string, string | number> | null;
    }

    let {
        reporteId,
        bounty,
        puedeAsignar = false,
        puedePagar = false,
        esInvestigador = false,
        severidad = null,
    }: {
        reporteId: number;
        bounty: BountyProps;
        puedeAsignar?: boolean;
        puedePagar?: boolean;
        esInvestigador?: boolean;
        severidad?: string | null;
    } = $props();

    let modalAsignar = $state(false);
    let modalPagar = $state(false);

    let montoAsignar = $state(bounty.monto ? String(bounty.monto) : sugerirMonto());
    let txHashPagar = $state('');
    let redPagar = $state(bounty.investigador_wallet_red ?? 'polygon');
    let procesando = $state(false);
    let errorAccion = $state('');
    let copiado = $state(false);

    function sugerirMonto(): string {
        if (!bounty.tabla_recompensas || !severidad) return '100';
        const valor = bounty.tabla_recompensas[severidad.toLowerCase()];
        return valor ? String(valor) : '100';
    }

    function copiarWallet() {
        if (!bounty.investigador_wallet) return;
        navigator.clipboard.writeText(bounty.investigador_wallet);
        copiado = true;
        setTimeout(() => (copiado = false), 2000);
    }

    function asignarBounty() {
        procesando = true;
        errorAccion = '';

        router.post(
            `/reportes/${reporteId}/bounty/asignar`,
            {
                monto: Number(montoAsignar),
                moneda: bounty.moneda || 'USDC',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    modalAsignar = false;
                },
                onError: (errs) => {
                    errorAccion = Object.values(errs)[0] as string;
                },
                onFinish: () => {
                    procesando = false;
                },
            }
        );
    }

    function pagarBounty() {
        procesando = true;
        errorAccion = '';

        router.post(
            `/reportes/${reporteId}/bounty/pagar`,
            {
                tx_hash: txHashPagar.trim(),
                red: redPagar,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    modalPagar = false;
                },
                onError: (errs) => {
                    errorAccion = Object.values(errs)[0] as string;
                },
                onFinish: () => {
                    procesando = false;
                },
            }
        );
    }
</script>

<Card class="border-border">
    <CardHeader class="pb-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="rounded-md bg-emerald-500/10 p-1.5 text-emerald-400">
                    <Coins class="h-4 w-4" />
                </div>
                <CardTitle class="text-sm font-semibold">Recompensa Económica (Bounty)</CardTitle>
            </div>

            {#if bounty.estado === 'pagado'}
                <Badge variant="outline" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-400 gap-1 text-xs">
                    <CheckCircle class="h-3 w-3" /> Pagado
                </Badge>
            {:else if bounty.estado === 'asignado'}
                <Badge variant="outline" class="border-amber-500/30 bg-amber-500/10 text-amber-400 gap-1 text-xs">
                    <AlertCircle class="h-3 w-3" /> Asignado (Pendiente de pago)
                </Badge>
            {:else}
                <Badge variant="outline" class="text-xs text-muted-foreground">
                    Sin asignar
                </Badge>
            {/if}
        </div>
        <CardDescription class="text-xs">
            {#if bounty.estado === 'pagado'}
                Recompensa pagada en {bounty.moneda} verificada en la red {bounty.red ?? 'blockchain'}.
            {:else if bounty.estado === 'asignado'}
                Monto fijado por la empresa. Pendiente de recepción de fondos en stablecoin.
            {:else}
                Este programa contempla recompensas económicas en criptoactivos (USDC).
            {/if}
        </CardDescription>
    </CardHeader>

    <CardContent class="space-y-4 pt-1">
        <!-- Detalles del monto -->
        {#if bounty.monto}
            <div class="rounded-lg border border-border/60 bg-muted/30 p-3 flex items-baseline justify-between">
                <span class="text-xs text-muted-foreground">Monto acordado:</span>
                <span class="font-mono text-lg font-bold text-foreground">
                    ${Number(bounty.monto).toLocaleString()} <span class="text-xs font-normal text-muted-foreground">{bounty.moneda}</span>
                </span>
            </div>
        {/if}

        <!-- Información de pago y Tx Hash -->
        {#if bounty.estado === 'pagado'}
            <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">Red de transferencia:</span>
                    <span class="font-medium uppercase text-emerald-400">{bounty.red ?? 'Polygon'}</span>
                </div>

                {#if bounty.tx_hash}
                    <div class="space-y-1">
                        <span class="text-[11px] text-muted-foreground">Hash de transacción (Tx Hash):</span>
                        <div class="flex items-center gap-2">
                            <code class="text-xs font-mono bg-background px-2 py-1 rounded border border-border/80 flex-1 truncate text-foreground" title={bounty.tx_hash}>
                                {bounty.tx_hash}
                            </code>
                            {#if bounty.explorer_url}
                                <a
                                    href={bounty.explorer_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs text-primary hover:underline shrink-0"
                                >
                                    Ver en explorador <ExternalLink class="h-3 w-3" />
                                </a>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Vista del Investigador si falta wallet -->
        {#if esInvestigador && bounty.estado !== 'pagado' && !bounty.investigador_wallet}
            <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 space-y-2 text-xs text-amber-300">
                <div class="flex items-center gap-1.5 font-semibold">
                    <AlertCircle class="h-4 w-4 shrink-0 text-amber-400" />
                    Billetera no configurada
                </div>
                <p class="text-[11px] leading-relaxed">
                    No has configurado tu dirección EVM en tu perfil. Para recibir el pago de recompensas por tus vulnerabilidades, agrégala en los ajustes de tu cuenta.
                </p>
                <Button variant="outline" size="sm" href="/settings/profile" class="text-xs h-7 border-amber-500/40 hover:bg-amber-500/20 text-amber-200">
                    Configurar billetera en mi perfil
                </Button>
            </div>
        {/if}

        <!-- Botones de Acción para la Empresa -->
        {#if puedeAsignar && bounty.estado !== 'pagado'}
            <Button
                variant="outline"
                size="sm"
                class="w-full text-xs"
                onclick={() => { modalAsignar = true; modalPagar = false; }}
            >
                <Coins class="mr-1.5 h-3.5 w-3.5 text-primary" />
                {bounty.estado === 'asignado' ? 'Modificar monto del Bounty' : 'Asignar monto del Bounty'}
            </Button>
        {/if}

        {#if puedePagar && bounty.estado === 'asignado'}
            <Button
                size="sm"
                class="w-full text-xs bg-emerald-600 hover:bg-emerald-500 text-white"
                onclick={() => { modalPagar = true; modalAsignar = false; }}
            >
                <Coins class="mr-1.5 h-3.5 w-3.5" />
                Registrar pago cripto (Tx Hash)
            </Button>
        {/if}

        <!-- Modal / Formulario para Asignar Bounty -->
        {#if modalAsignar}
            <div class="rounded-lg border border-border bg-card p-3 space-y-3 mt-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-foreground">Fijar recompensa del reporte</h4>
                    <Button variant="ghost" size="sm" class="h-6 w-6 p-0 text-muted-foreground" onclick={() => modalAsignar = false}>✕</Button>
                </div>

                {#if errorAccion}
                    <p class="text-xs text-destructive">{errorAccion}</p>
                {/if}

                <div class="space-y-1">
                    <Label for="monto_input" class="text-xs">Monto en {bounty.moneda}</Label>
                    <div class="relative">
                        <span class="absolute left-2.5 top-2 text-xs text-muted-foreground">$</span>
                        <Input
                            id="monto_input"
                            type="number"
                            min="1"
                            step="1"
                            class="pl-6 h-8 text-xs font-mono"
                            bind:value={montoAsignar}
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <Button variant="ghost" size="sm" class="text-xs h-7" onclick={() => modalAsignar = false}>Cancelar</Button>
                    <Button size="sm" class="text-xs h-7" disabled={procesando || !montoAsignar} onclick={asignarBounty}>
                        {#if procesando}<Spinner class="mr-1 h-3 w-3" />{/if}
                        Guardar monto
                    </Button>
                </div>
            </div>
        {/if}

        <!-- Modal / Formulario para Pagar Bounty con Tx Hash -->
        {#if modalPagar}
            <div class="rounded-lg border border-border bg-card p-3 space-y-3 mt-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-foreground">Confirmar transferencia cripto</h4>
                    <Button variant="ghost" size="sm" class="h-6 w-6 p-0 text-muted-foreground" onclick={() => modalPagar = false}>✕</Button>
                </div>

                {#if errorAccion}
                    <p class="text-xs text-destructive">{errorAccion}</p>
                {/if}

                <!-- Datos de la wallet del investigador -->
                <div class="rounded-md bg-muted/50 p-2.5 space-y-2 text-xs">
                    <span class="text-[11px] text-muted-foreground block">Dirección de pago del investigador:</span>
                    {#if bounty.investigador_wallet}
                        <div class="flex items-center gap-2">
                            <code class="font-mono text-xs bg-background px-2 py-1 rounded border border-border flex-1 truncate text-foreground">
                                {bounty.investigador_wallet}
                            </code>
                            <Button variant="outline" size="sm" class="h-7 px-2 shrink-0" onclick={copiarWallet}>
                                {#if copiado}
                                    <Check class="h-3 w-3 text-emerald-400" />
                                {:else}
                                    <Copy class="h-3 w-3" />
                                {/if}
                            </Button>
                        </div>
                        <p class="text-[10px] text-muted-foreground">
                            Red preferida por el hacker: <strong class="uppercase text-foreground">{bounty.investigador_wallet_red ?? 'Polygon'}</strong>
                        </p>
                    {:else}
                        <p class="text-destructive font-medium">
                            El investigador aún no ha guardado su dirección de billetera en su perfil. Pídele que la registre antes de realizar la transacción.
                        </p>
                    {/if}
                </div>

                <!-- Input Tx Hash -->
                <div class="space-y-1">
                    <Label for="tx_hash_input" class="text-xs">Hash de la transacción (Tx Hash)</Label>
                    <Input
                        id="tx_hash_input"
                        placeholder="0x4a7b...89c0"
                        class="h-8 text-xs font-mono"
                        bind:value={txHashPagar}
                    />
                    <p class="text-[10px] text-muted-foreground">
                        Pega el identificador de transacción generado por tu wallet (MetaMask, Ledger, etc.).
                    </p>
                </div>

                <!-- Red usada -->
                <div class="space-y-1">
                    <Label for="red_select" class="text-xs">Red blockchain utilizada</Label>
                    <select
                        id="red_select"
                        class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                        bind:value={redPagar}
                    >
                        <option value="polygon">Polygon PoS</option>
                        <option value="amoy">Polygon Amoy Testnet (Pruebas)</option>
                        <option value="arbitrum">Arbitrum One</option>
                        <option value="base">Base</option>
                        <option value="ethereum">Ethereum</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <Button variant="ghost" size="sm" class="text-xs h-7" onclick={() => modalPagar = false}>Cancelar</Button>
                    <Button
                        size="sm"
                        class="text-xs h-7 bg-emerald-600 hover:bg-emerald-500 text-white"
                        disabled={procesando || !txHashPagar.trim()}
                        onclick={pagarBounty}
                    >
                        {#if procesando}<Spinner class="mr-1 h-3 w-3" />{/if}
                        Confirmar y verificar
                    </Button>
                </div>
            </div>
        {/if}
    </CardContent>
</Card>
