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
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import Inbox from '@lucide/svelte/icons/inbox';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import type { EstadoPrograma, EstadoReporte, Severidad } from '@/types/enums';

    type Filtro = 'por_revisar' | 'en_revision' | 'aprobados' | 'rechazados' | 'todos';

    type ReporteCola = {
        id: number;
        numero_reporte: string;
        titulo: string;
        estado: EstadoReporte;
        severidad: Severidad | null;
        categoria: string | null;
        enviado_en: string | null;
        es_duplicado_de: number | null;
        asignado_a: { id: number; name: string } | null;
        investigador: {
            id: number;
            name: string;
            reputation_score: number;
            reportes_descartados: number;
        };
    };

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
            data: ReporteCola[];
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

    function formatearFecha(fecha: string | null): string {
        if (!fecha) return 'Sin enviar';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(fecha));
    }
</script>

<AppHead title={`Moderación — ${programa.nombre}`} />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" href="/moderacion" aria-label="Volver a moderación">
            <ArrowLeft class="h-4 w-4" />
        </Button>
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
        <div class="space-y-3">
            {#each reportes.data as reporte (reporte.id)}
                <Card>
                    <CardContent class="flex flex-col gap-3 pt-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link href={`/reportes/${reporte.id}`} class="font-medium hover:underline">
                                    {reporte.numero_reporte} · {reporte.titulo}
                                </Link>
                                <StateBadge estado={reporte.estado} />
                                {#if reporte.severidad}<SeverityBadge severidad={reporte.severidad} />{/if}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Investigador: {reporte.investigador.name}
                                (reputación {reporte.investigador.reputation_score}
                                {#if reporte.investigador.reportes_descartados > 0}
                                    · <span class="text-chart-4">{reporte.investigador.reportes_descartados} informes descartados</span>
                                {/if})
                                · Enviado: {formatearFecha(reporte.enviado_en)}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {reporte.categoria ?? 'Sin categoría'}
                                {#if reporte.asignado_a} · Asignado a {reporte.asignado_a.name}{/if}
                                {#if reporte.es_duplicado_de} · Duplicado del informe #{reporte.es_duplicado_de}{/if}
                            </p>
                        </div>
                        <Button href={`/reportes/${reporte.id}`} variant={reporte.estado === 'enviado' ? 'default' : 'outline'}>
                            {reporte.estado === 'enviado' || reporte.estado === 'en_revision' ? 'Revisar informe' : 'Ver informe'}
                        </Button>
                    </CardContent>
                </Card>
            {/each}
        </div>

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
