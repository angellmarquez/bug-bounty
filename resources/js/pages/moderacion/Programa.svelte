<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Moderación', href: '/moderacion' },
            { title: 'Programa' },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Inbox from '@lucide/svelte/icons/inbox';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ReportesCompactos from '@/components/ReportesCompactos.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import { Button } from '@/components/ui/button';
    import type { EstadoPrograma } from '@/types/enums';
    import type { ReporteCompacto } from '@/types/domain';

    type Filtro = 'por_revisar' | 'en_revision' | 'aprobados' | 'rechazados' | 'todos';

    let {
        programa,
        filtro,
        conteos,
        reportes,
    }: {
        programa: { id: number; nombre: string; estado: EstadoPrograma; empresa: string | null };
        filtro: Filtro;
        conteos: Record<Filtro, number>;
        reportes: {
            data: ReporteCompacto[];
            last_page: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
    } = $props();

    const pestanas: { valor: Filtro; etiqueta: string }[] = [
        { valor: 'por_revisar', etiqueta: 'Por revisar' },
        { valor: 'en_revision', etiqueta: 'En revisión' },
        { valor: 'aprobados', etiqueta: 'Aprobados' },
        { valor: 'rechazados', etiqueta: 'Rechazados' },
        { valor: 'todos', etiqueta: 'Todos' },
    ];
</script>

<AppHead title={`Moderación — ${programa.nombre}`} />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver href={'/moderacion'} etiqueta="Volver a moderación" />
        <PageHeader
            title={programa.nombre}
            description={`Informes enviados a ${programa.empresa ?? 'la empresa'}`}
        />
        <ProgramaStateBadge estado={programa.estado} />
    </div>

    <div class="flex flex-wrap gap-2">
        {#each pestanas as pestana (pestana.valor)}
            <Button
                size="sm"
                variant={filtro === pestana.valor ? 'default' : 'outline'}
                href={`/moderacion/programas/${programa.id}?filtro=${pestana.valor}`}
            >
                {pestana.etiqueta} ({conteos[pestana.valor]})
            </Button>
        {/each}
    </div>

    {#if reportes.data.length === 0}
        <EmptyState
            icon={Inbox}
            title="No hay informes en esta vista"
            description="Cuando los investigadores envíen informes a este programa aparecerán aquí."
        />
    {:else}
        <ReportesCompactos
            reportes={reportes.data}
            mostrarPrograma={false}
            vistaRapida
            destacar={(reporte) => reporte.estado === 'enviado'}
            etiquetaAccion={(reporte) =>
                reporte.estado === 'enviado' || reporte.estado === 'en_revision' ? 'Revisar informe' : 'Ver informe'}
        />

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
