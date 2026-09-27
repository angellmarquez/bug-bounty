<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Configuración del plan', href: '/admin/config/plan' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import type { RedBounty } from '@/types/domain';

    let {
        config,
        red = null,
    }: {
        config: { tesoreria: string | null; precio_usdc: number; dias: number; actualizado_en: string | null; actualizado_por: string | null };
        red?: RedBounty | null;
    } = $props();

    // Copia editable del formulario: no se sincroniza con las props al guardar (la página se recarga).
    const inicial = $state.snapshot(config);
    let tesoreria = $state(inicial.tesoreria ?? '');
    let precio = $state(String(inicial.precio_usdc));
    let dias = $state(String(inicial.dias));
    let password = $state('');
    let errores = $state<Record<string, string>>({});
    let guardando = $state(false);

    const cambiaTesoreria = $derived((tesoreria.trim().toLowerCase() || null) !== (config.tesoreria ?? null));

    function guardar(e: SubmitEvent) {
        e.preventDefault();
        guardando = true;
        errores = {};
        router.put(
            '/admin/config/plan',
            { tesoreria: tesoreria.trim() || null, precio_usdc: precio, dias, password },
            {
                preserveScroll: true,
                onError: (e) => (errores = e as Record<string, string>),
                onSuccess: () => (password = ''),
                onFinish: () => (guardando = false),
            },
        );
    }
</script>

<AppHead title="Configuración del plan" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <PageHeader title="Configuración del plan" description="A qué wallet del proyecto se paga el Plan Profesional y cuánto cuesta." />

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
            <CardHeader>
                <CardTitle>Plan Profesional</CardTitle>
                {#if config.actualizado_en}
                    <CardDescription>
                        Última modificación: {new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(config.actualizado_en))}{config.actualizado_por ? ` por ${config.actualizado_por}` : ''}.
                    </CardDescription>
                {/if}
            </CardHeader>
            <CardContent>
                <form class="space-y-5" onsubmit={guardar}>
                    <div class="space-y-2">
                        <Label for="tesoreria">Wallet de tesorería (dirección pública)</Label>
                        <Input id="tesoreria" bind:value={tesoreria} placeholder="0x…" class="font-mono text-xs" maxlength={42} autocomplete="off" spellcheck="false" />
                        <p class="text-xs text-muted-foreground">
                            Aquí llegan los pagos del plan{red ? ` en USDC sobre ${red.nombre}` : ''}. Solo se guarda la dirección: la plataforma nunca tiene la clave privada.
                        </p>
                        <InputError message={errores.tesoreria} />
                        {#if config.tesoreria && red}
                            <a href={`${red.explorer_url}/address/${config.tesoreria}`} target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                Ver saldo actual en el explorador <ExternalLink class="h-3 w-3" />
                            </a>
                        {/if}
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="precio">Precio (USDC)</Label>
                            <Input id="precio" type="number" step="0.01" min="0.01" bind:value={precio} required />
                            <InputError message={errores.precio_usdc} />
                        </div>
                        <div class="space-y-2">
                            <Label for="dias">Duración (días)</Label>
                            <Input id="dias" type="number" min="1" max="366" bind:value={dias} required />
                            <InputError message={errores.dias} />
                        </div>
                    </div>

                    {#if cambiaTesoreria}
                        <div class="flex items-start gap-2 rounded-md border border-aviso/40 bg-aviso/10 p-3 text-xs" role="alert" data-test="aviso-cambio-tesoreria">
                            <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0 text-aviso" />
                            <p>Vas a cambiar la wallet que recibe los pagos. Comprueba la dirección carácter por carácter: los pagos enviados a una dirección equivocada no se pueden recuperar. Se avisará a todos los administradores.</p>
                        </div>
                    {/if}

                    <div class="space-y-2">
                        <Label for="password">Tu contraseña (para confirmar)</Label>
                        <Input id="password" type="password" bind:value={password} autocomplete="current-password" required />
                        <InputError message={errores.password} />
                    </div>

                    <Button type="submit" disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="text-base">Cómo proteger la tesorería</CardTitle></CardHeader>
            <CardContent class="space-y-2 text-xs text-muted-foreground">
                <p>Usa una <strong class="text-foreground">wallet física</strong> (Ledger, Trezor) o una <strong class="text-foreground">multifirma Safe</strong> (safe.global), por ejemplo 2 de 3 personas.</p>
                <p>Nunca pongas la frase de recuperación ni la clave privada en el servidor, en el <code>.env</code>, en GitHub ni en un chat.</p>
                <p>Para pruebas en testnet basta una cuenta de MetaMask aparte, llamada por ejemplo "Proyecto".</p>
            </CardContent>
        </Card>
    </div>
</div>
