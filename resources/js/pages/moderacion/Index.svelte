<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Moderación', href: '/moderacion' }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
    import type { EstadoPrograma } from '@/types/enums';

    type ProgramaModeracion = {
        id: number;
        nombre: string;
        estado: EstadoPrograma;
        empresa: string | null;
        reportes_total: number;
        reportes_por_revisar: number;
        reportes_en_revision: number;
        reportes_aprobados: number;
        reportes_rechazados: number;
    };

    let {
        programas,
        filtros,
        resumen,
    }: {
        programas: {
            data: ProgramaModeracion[];
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
        filtros: { busqueda: string; solo_pendientes: boolean };
        resumen: {
            por_revisar: number;
            en_revision: number;
            mis_asignados: number;
            aprobados: number;
            rechazados: number;
        };
    } = $props();

    let busqueda = $state(filtros.busqueda);
    let soloPendientes = $state(filtros.solo_pendientes);

    const tarjetas = $derived([
        { titulo: 'Por revisar', valor: resumen.por_revisar, clase: 'text-chart-4' },
        { titulo: 'En revisión', valor: resumen.en_revision, clase: 'text-chart-2' },
        { titulo: 'Asignados a mí', valor: resumen.mis_asignados, clase: 'text-primary' },
        { titulo: 'Aprobados', valor: resumen.aprobados, clase: 'text-chart-1' },
        { titulo: 'Rechazados', valor: resumen.rechazados, clase: 'text-muted-foreground' },
    ]);

    function filtrar(event?: Event) {
        event?.preventDefault();
        router.get(
            '/moderacion',
            { busqueda: busqueda || undefined, solo_pendientes: soloPendientes ? 1 : undefined },
            { preserveState: true, replace: true },
        );
    }
</script>

<AppHead title="Moderación" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Moderación"
        description="Elige un programa para revisar los informes que los investigadores enviaron a su empresa."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        {#each tarjetas as tarjeta (tarjeta.titulo)}
            <Card>
                <CardHeader class="pb-2"><CardDescription>{tarjeta.titulo}</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold {tarjeta.clase}">{tarjeta.valor}</p></CardContent>
            </Card>
        {/each}
    </div>

    <form class="flex flex-wrap items-center gap-3" onsubmit={filtrar}>
        <div class="relative min-w-60 flex-1">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input bind:value={busqueda} placeholder="Buscar programa..." class="pl-9" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" bind:checked={soloPendientes} onchange={() => filtrar()} />
            Solo con informes pendientes
        </label>
        <Button type="submit" variant="outline">Buscar</Button>
    </form>

    {#if programas.data.length === 0}
        <EmptyState
            icon={ClipboardCheck}
            title="No hay programas"
            description="Ningún programa coincide con los filtros."
        />
    {:else}
        <div class="space-y-3">
            {#each programas.data as programa (programa.id)}
                <Card>
                    <CardContent class="flex flex-col gap-3 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link href={`/moderacion/programas/${programa.id}`} class="font-medium hover:underline">
                                    {programa.nombre}
                                </Link>
                                <ProgramaStateBadge estado={programa.estado} />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {programa.empresa ?? 'Sin empresa'} · {programa.reportes_total} informes ·
                                <span class="text-chart-4">{programa.reportes_por_revisar} por revisar</span> ·
                                {programa.reportes_en_revision} en revisión ·
                                <span class="text-chart-1">{programa.reportes_aprobados} aprobados</span> ·
                                {programa.reportes_rechazados} rechazados
                            </p>
                        </div>
                        <Button href={`/moderacion/programas/${programa.id}`} variant={programa.reportes_por_revisar > 0 ? 'default' : 'outline'}>
                            Ver informes
                        </Button>
                    </CardContent>
                </Card>
            {/each}
        </div>

        {#if programas.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each programas.links as link (link.label)}
                    {#if link.url}
                        <Link
                            href={link.url}
                            class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors hover:bg-secondary {link.active ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground'}"
                        >
                            {@html link.label}
                        </Link>
                    {:else}
                        <span class="inline-flex h-9 items-center justify-center px-3 text-sm text-muted-foreground">
                            {@html link.label}
                        </span>
                    {/if}
                {/each}
            </nav>
        {/if}
    {/if}
</div>
