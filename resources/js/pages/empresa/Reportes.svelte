<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Empresa', href: '/empresa' },
            { title: 'Informes recibidos' },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Inbox from '@lucide/svelte/icons/inbox';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ReportesCompactos from '@/components/ReportesCompactos.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import type { ReporteCompacto } from '@/types/domain';

    type Filtro = 'todos' | 'pendientes' | 'aprobados' | 'rechazados' | 'cerrados';

    let {
        empresa,
        programas,
        filtros,
        conteos,
        reportes,
    }: {
        empresa: { id: number; nombre: string };
        programas: { id: number; nombre: string }[];
        filtros: { filtro: Filtro; programa_id: number | null; busqueda: string };
        conteos: Record<Filtro, number>;
        reportes: {
            data: ReporteCompacto[];
            total: number;
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    } = $props();

    const pestanas: { valor: Filtro; etiqueta: string }[] = [
        { valor: 'todos', etiqueta: 'Todos' },
        { valor: 'pendientes', etiqueta: 'Pendientes de moderación' },
        { valor: 'aprobados', etiqueta: 'Aprobados por moderadores' },
        { valor: 'rechazados', etiqueta: 'Rechazados' },
        { valor: 'cerrados', etiqueta: 'Cerrados' },
    ];

    let busqueda = $state(filtros.busqueda);
    let programaId = $state(filtros.programa_id ? String(filtros.programa_id) : '');

    function consulta(filtro: Filtro): string {
        const params = new URLSearchParams();
        if (filtro !== 'todos') params.set('filtro', filtro);
        if (programaId) params.set('programa_id', programaId);
        if (busqueda) params.set('busqueda', busqueda);
        const texto = params.toString();
        return `/empresa/reportes${texto ? `?${texto}` : ''}`;
    }

    function filtrar(event?: Event) {
        event?.preventDefault();
        router.get(consulta(filtros.filtro), {}, { preserveState: true, replace: true });
    }
</script>

<AppHead title="Informes recibidos" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver href={'/empresa'} etiqueta="Volver al panel de empresa" />
        <PageHeader
            title="Informes recibidos"
            description={`${empresa.nombre} · lo que los investigadores encontraron en tus programas`}
        />
    </div>

    <div class="flex flex-wrap gap-2">
        {#each pestanas as pestana (pestana.valor)}
            <Button
                size="sm"
                variant={filtros.filtro === pestana.valor ? 'default' : 'outline'}
                href={consulta(pestana.valor)}
            >
                {pestana.etiqueta} ({conteos[pestana.valor]})
            </Button>
        {/each}
    </div>

    <form class="flex flex-wrap items-center gap-3" onsubmit={filtrar}>
        <div class="relative min-w-60 flex-1">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input bind:value={busqueda} placeholder="Buscar por título o número..." class="pl-9" />
        </div>
        {#if programas.length > 1}
            <select
                bind:value={programaId}
                onchange={() => filtrar()}
                aria-label="Filtrar por programa"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
            >
                <option value="">Todos los programas</option>
                {#each programas as programa (programa.id)}
                    <option value={String(programa.id)}>{programa.nombre}</option>
                {/each}
            </select>
        {/if}
        <Button type="submit" variant="outline">Buscar</Button>
    </form>

    {#if reportes.data.length === 0}
        <EmptyState
            icon={Inbox}
            title="No hay informes en esta vista"
            description="Cuando los investigadores envíen informes a tus programas aparecerán aquí."
        />
    {:else}
        <ReportesCompactos reportes={reportes.data} />

        {#if reportes.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each reportes.links as link (link.label)}
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
