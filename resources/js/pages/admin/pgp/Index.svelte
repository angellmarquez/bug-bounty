<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
            },
            {
                title: 'PGP Plataforma',
                href: '/admin/pgp',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Key from '@lucide/svelte/icons/key';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';

    let {
        clave,
        driver,
        available,
    }: {
        clave: {
            id: number;
            huella: string;
            identidad: string | null;
            algoritmo: string | null;
            bits: number | null;
            creada_en: string | null;
            expira_en: string | null;
        } | null;
        driver: string;
        available: boolean;
    } = $props();

    let generando = $state(false);

    function generarClave() {
        generando = true;
        router.post('/admin/pgp/setup', {}, {
            preserveState: true,
            onFinish: () => {
                generando = false;
            },
        });
    }

    function formatearFecha(dateStr: string | null): string {
        if (!dateStr || Number.isNaN(new Date(dateStr).getTime())) return 'Sin fecha';

        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }

    const isFallback = $derived(driver === 'fallback');
</script>

<AppHead title="PGP Plataforma" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="PGP Plataforma"
        description="Gestion del par de claves PGP de la plataforma para cifrado interno"
    />

    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-lg">
                <Key class="h-5 w-5 text-primary" />
                Estado del Driver
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full {available ? 'bg-chart-1/20' : 'bg-chart-3/20'}">
                    {#if available}
                        <ShieldCheck class="h-5 w-5 text-chart-1" />
                    {:else}
                        <AlertTriangle class="h-5 w-5 text-chart-3" />
                    {/if}
                </div>
                <div>
                    <p class="text-sm font-medium">Driver: <span class="font-mono text-primary">{driver}</span></p>
                    <p class="text-xs text-muted-foreground">
                        {available ? 'Disponible y operativo' : 'No disponible en este entorno'}
                    </p>
                </div>
            </div>

            {#if isFallback}
                <div class="rounded-md border border-chart-4/50 bg-chart-4/10 px-4 py-3 text-sm text-chart-4">
                    <p class="font-semibold">Modo fallback activo</p>
                    <p class="mt-1 text-xs text-chart-4/80">
                        El driver fallback no ofrece confidencialidad real. Instala Gpg4win y configura PGP_DRIVER=auto para produccion.
                    </p>
                </div>
            {/if}
        </CardContent>
    </Card>

    {#if clave}
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <ShieldCheck class="h-5 w-5 text-chart-1" />
                    Clave Activa
                </CardTitle>
                <CardDescription>
                    Par de claves PGP de la plataforma generado y activo
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <p class="text-xs text-muted-foreground">Huella</p>
                        <p class="font-mono text-sm break-all">{clave.huella}</p>
                    </div>
                    {#if clave.identidad}
                        <div class="space-y-1">
                            <p class="text-xs text-muted-foreground">Identidad</p>
                            <p class="font-mono text-sm">{clave.identidad}</p>
                        </div>
                    {/if}
                    {#if clave.algoritmo}
                        <div class="space-y-1">
                            <p class="text-xs text-muted-foreground">Algoritmo</p>
                            <p class="text-sm">{clave.algoritmo}</p>
                        </div>
                    {/if}
                    {#if clave.bits}
                        <div class="space-y-1">
                            <p class="text-xs text-muted-foreground">Bits</p>
                            <p class="text-sm">{clave.bits}</p>
                        </div>
                    {/if}
                    <div class="space-y-1">
                        <p class="text-xs text-muted-foreground">Generada</p>
                        <p class="text-sm">{formatearFecha(clave.creada_en)}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    {:else}
        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Sin Clave Configurada</CardTitle>
                <CardDescription>
                    La plataforma no tiene un par de claves PGP generado. Sin la clave, el cifrado interno no esta disponible.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Button
                    onclick={generarClave}
                    disabled={generando || !available}
                    size="lg"
                >
                    {#if generando}
                        Generando...
                    {:else}
                        Generar Par de Claves
                    {/if}
                </Button>
                {#if !available}
                    <p class="mt-2 text-xs text-muted-foreground">
                        El driver PGP no esta disponible en este entorno.
                    </p>
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>
