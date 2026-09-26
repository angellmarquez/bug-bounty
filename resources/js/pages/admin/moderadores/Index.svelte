<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Admin' },
            { title: 'Moderadores', href: '/admin/moderadores' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import type { User } from '@/types/auth';

    let {
        moderadores: moderadoresData,
        usuariosDisponibles,
        programas,
        limitePorModerador = 5,
        programasSinModerador = [],
    }: {
        moderadores: { data: (User & { programas_activos_count?: number })[]; total: number };
        usuariosDisponibles: User[];
        programas: { id: number; nombre: string; estado?: string; moderadores: { id: number }[] }[];
        limitePorModerador?: number;
        programasSinModerador?: { id: number; nombre: string }[];
    } = $props();

    let programaSeleccionado = $state<Record<number, string>>({});

    function asignar(userId: number) {
        router.post(`/admin/moderadores/${userId}`, {}, { preserveState: true });
    }

    function revocar(userId: number) {
        router.delete(`/admin/moderadores/${userId}`, { preserveState: true });
    }

    function asignarAPrograma(userId: number) {
        const programaId = programaSeleccionado[userId];
        if (!programaId) return;
        router.post(`/admin/programas/${programaId}/moderadores/${userId}`, {}, { preserveState: true });
    }

    function quitarDePrograma(userId: number, programaId: number) {
        router.delete(`/admin/programas/${programaId}/moderadores/${userId}`, { preserveState: true });
    }

    function programasDe(userId: number) {
        return programas.filter((programa) => programa.moderadores.some((moderador) => moderador.id === userId));
    }

    function estaAsignado(programa: { moderadores: { id: number }[] }, userId: number): boolean {
        return programa.moderadores.some((moderador) => moderador.id === userId);
    }
</script>

<AppHead title="Moderadores" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <PageHeader title="Moderadores" description={`${moderadoresData.total} moderador${moderadoresData.total === 1 ? '' : 'es'} asignado${moderadoresData.total === 1 ? '' : 's'}`} />

    {#if programasSinModerador.length > 0}
        <div class="flex items-start gap-3 rounded-lg border border-chart-4/50 bg-chart-4/10 p-4 text-sm text-chart-4">
            <AlertTriangle class="h-5 w-5 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-semibold">Capacidad de moderación alcanzada</p>
                <p class="text-xs text-muted-foreground">
                    Hay <strong>{programasSinModerador.length} programa(s) activo(s)</strong> sin moderadores asignados porque todos los moderadores activos han alcanzado su límite recomendado ({limitePorModerador} programas activos). Apenas un moderador se libere o desasigne de un programa, el sistema le asignará estos programas pendientes automáticamente.
                </p>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    {#each programasSinModerador as prog (prog.id)}
                        <span class="rounded bg-background/80 px-2 py-0.5 text-xs font-mono font-medium border border-border">
                            {prog.nombre}
                        </span>
                    {/each}
                </div>
            </div>
        </div>
    {/if}

    <Card>
        <CardHeader><CardTitle>Asignar moderador</CardTitle></CardHeader>
        <CardContent class="space-y-2">
            {#if usuariosDisponibles.length === 0}
                <p class="text-sm text-muted-foreground">
                    No hay investigadores disponibles. Registra un investigador desde
                    <a href="/register" class="underline">/register</a> o ejecuta los seeders de desarrollo.
                </p>
            {:else}
                {#each usuariosDisponibles as usuario (usuario.id)}
                    <div class="flex items-center justify-between gap-3 border-b border-border py-2 last:border-0">
                        <div><p class="text-sm font-medium">{usuario.name}</p><p class="text-xs text-muted-foreground">{usuario.email}</p></div>
                        <Button size="sm" onclick={() => asignar(usuario.id)}>Asignar</Button>
                    </div>
                {/each}
            {/if}
        </CardContent>
    </Card>

    {#if moderadoresData.data.length === 0}
        <EmptyState icon={ShieldCheck} title="No hay moderadores" description="Aún no se han asignado moderadores." />
    {:else}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {#each moderadoresData.data as moderador (moderador.id)}
                <Card>
                    <CardContent class="space-y-4 pt-6">
                        <div class="flex items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium">{moderador.name}</p>
                                <p class="text-xs text-muted-foreground">{moderador.email}</p>
                                <p class="text-[11px] {(moderador.programas_activos_count ?? 0) >= limitePorModerador ? 'text-chart-4 font-semibold' : 'text-muted-foreground'}">
                                    Carga: {moderador.programas_activos_count ?? 0} / {limitePorModerador} programas activos
                                </p>
                            </div>
                            <Button size="sm" variant="destructive" onclick={() => revocar(moderador.id)}>Revocar</Button>
                        </div>
                        <div class="space-y-1" data-test="programas-moderados">
                            <p class="text-xs font-medium text-muted-foreground">Programas que modera</p>
                            {#each programasDe(moderador.id) as programa (programa.id)}
                                <div class="flex items-center justify-between gap-2 rounded-md bg-muted px-2 py-1 text-sm">
                                    <span class="truncate">{programa.nombre}</span>
                                    <Button size="sm" variant="ghost" class="h-6 px-2 text-xs" aria-label="Quitar {programa.nombre}" onclick={() => quitarDePrograma(moderador.id, programa.id)}>
                                        Quitar
                                    </Button>
                                </div>
                            {:else}
                                <p class="text-xs text-muted-foreground">
                                    Ninguno todavía: no ve ningún informe hasta que le asignes un programa. Como moderador de un programa no
                                    podrá enviar informes a él.
                                </p>
                            {/each}
                        </div>
                        <div class="flex gap-2">
                            <select class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm" bind:value={programaSeleccionado[moderador.id]}>
                                <option value="">Seleccionar programa</option>
                                {#each programas.filter((programa) => !estaAsignado(programa, moderador.id)) as programa (programa.id)}
                                    <option value={String(programa.id)}>{programa.nombre}</option>
                                {/each}
                            </select>
                            <Button size="sm" onclick={() => asignarAPrograma(moderador.id)} disabled={!programaSeleccionado[moderador.id]}>Asignar alcance</Button>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>