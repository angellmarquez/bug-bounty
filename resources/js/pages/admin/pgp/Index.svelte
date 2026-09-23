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
    import Key from '@lucide/svelte/icons/key';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import Building2 from '@lucide/svelte/icons/building-2';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
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
        empresas,
    }: {
        clave: {
            id: number;
            expira_en: string | null;
        } | null;
        driver: string;
        available: boolean;
        empresas: { id: number; nombre: string; tiene_clave: boolean; expira_en: string | null }[];
    } = $props();

    const isFallback = $derived(driver === 'fallback');

    // "Funcionando" = hay clave, el driver está disponible y no expiró.
    const expirada = $derived.by(() => {
        if (!clave?.expira_en) return false;
        const fecha = new Date(clave.expira_en);
        return !Number.isNaN(fecha.getTime()) && fecha.getTime() < Date.now();
    });
    const funcionando = $derived(Boolean(clave) && available && !expirada);
</script>

<AppHead title="PGP Plataforma" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="PGP Plataforma"
        description="Estado del cifrado interno. La plataforma crea y gestiona cada clave sola: no requiere ninguna acción."
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
                    <ShieldCheck class="h-5 w-5 {funcionando ? 'text-chart-1' : 'text-chart-3'}" />
                    Clave de Custodia
                </CardTitle>
                <CardDescription>
                    La usan moderación y el admin como segundo destinatario (junto a la clave de cada empresa) para poder
                    auditar y resolver apelaciones. La clave privada nunca se muestra.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full {funcionando ? 'bg-chart-1/20' : 'bg-chart-3/20'}">
                    {#if funcionando}
                        <ShieldCheck class="h-5 w-5 text-chart-1" />
                    {:else}
                        <AlertTriangle class="h-5 w-5 text-chart-3" />
                    {/if}
                </div>
                <div>
                    <p class="text-sm font-medium">{funcionando ? 'Clave activa y funcionando' : 'Clave activa, pero con problemas'}</p>
                    <p class="text-xs text-muted-foreground">
                        {#if expirada}
                            La clave expiró: la plataforma debe generar una nueva.
                        {:else if !available}
                            El driver no está disponible en este entorno.
                        {:else}
                            El cifrado de reportes está operativo.
                        {/if}
                    </p>
                </div>
            </CardContent>
        </Card>
    {:else}
        <div data-test="sin-clave">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Aún no hay clave</CardTitle>
                    <CardDescription>
                        La plataforma crea su clave de cifrado automáticamente: al instalarse o al recibir el primer informe. No hace
                        falta ninguna acción.
                    </CardDescription>
                </CardHeader>
                {#if !available}
                    <CardContent>
                        <p class="text-sm text-chart-3" data-test="driver-no-disponible">
                            El driver PGP no está disponible en este entorno, así que la clave no se puede crear todavía. Mientras tanto,
                            ningún informe se guarda sin cifrar.
                        </p>
                    </CardContent>
                {/if}
            </Card>
        </div>
    {/if}

    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-lg">
                <Building2 class="h-5 w-5 text-primary" />
                Claves por Empresa
            </CardTitle>
            <CardDescription>
                Cada empresa tiene su propia clave: una fuga no compromete a las demás. Se crea sola al publicar su primer
                programa.
            </CardDescription>
        </CardHeader>
        <CardContent>
            {#if empresas.length === 0}
                <EmptyState
                    icon={Building2}
                    title="Todavía no hay empresas con programas"
                    description="La clave de cada empresa se genera sola al publicar su primer programa."
                />
            {:else}
                <div class="space-y-2">
                    {#each empresas as empresa (empresa.id)}
                        <div class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                            <span class="font-medium">{empresa.nombre}</span>
                            {#if empresa.tiene_clave}
                                <span class="inline-flex items-center gap-1 rounded-md border border-chart-1/40 bg-chart-1/10 px-2 py-0.5 text-xs font-semibold text-chart-1">
                                    <ShieldCheck class="h-3 w-3" />
                                    Con clave
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-md border border-muted-foreground/30 bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                                    Sin clave todavía
                                </span>
                            {/if}
                        </div>
                    {/each}
                </div>
            {/if}
        </CardContent>
    </Card>
</div>
