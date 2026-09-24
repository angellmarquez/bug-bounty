<script module lang="ts">
    import { index as reportesIndex } from '@/routes/reportes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reportes',
                href: reportesIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import Bug from '@lucide/svelte/icons/bug';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Input } from '@/components/ui/input';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { index as reportesRoute, create as reportesCreate, show as reportesShow } from '@/routes/reportes';
    import { Button } from '@/components/ui/button';
    import Plus from '@lucide/svelte/icons/plus';
    import type { Reporte } from '@/types/domain';
    import { etiquetaPaginacion } from '@/lib/paginacion';

    const ESTADOS_FILTRO = [
        { value: 'todos', label: 'Todos los estados' },
        { value: 'borrador', label: 'Borrador' },
        { value: 'enviado', label: 'Enviado' },
        { value: 'en_revision', label: 'En revision' },
        { value: 'validado', label: 'Validado' },
        { value: 'en_reparacion', label: 'En reparacion' },
        { value: 'rechazado', label: 'Rechazado' },
        { value: 'duplicado', label: 'Duplicado' },
        { value: 'fuera_de_alcance', label: 'Fuera de alcance' },
        { value: 'cerrado', label: 'Resuelto' },
    ];

    const SEVERIDADES_FILTRO = [
        { value: 'todos', label: 'Todas las severidades' },
        { value: 'critica', label: 'Critica' },
        { value: 'alta', label: 'Alta' },
        { value: 'media', label: 'Media' },
        { value: 'baja', label: 'Baja' },
        { value: 'ninguna', label: 'Ninguna' },
    ];

    let {
        reportes: reportesData,
        filtros,
        programas,
        puedeCrear = false,
    }: {
        reportes: {
            data: Reporte[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        filtros: {
            estado?: string;
            severidad?: string;
            programa_id?: string;
            busqueda?: string;
        };
        programas: { id: number; nombre: string }[];
        puedeCrear?: boolean;
    } = $props();

    let busqueda = $state(filtros.busqueda ?? '');

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.estado) params.estado = filtros.estado;
        if (filtros.severidad) params.severidad = filtros.severidad;
        if (filtros.programa_id) params.programa_id = filtros.programa_id;
        if (filtros.busqueda) params.busqueda = filtros.busqueda;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get(reportesRoute(), params, {
            preserveState: true,
            replace: true,
        });
    }

    function buscar() {
        const params: Record<string, string> = {};
        if (filtros.estado) params.estado = filtros.estado;
        if (filtros.severidad) params.severidad = filtros.severidad;
        if (filtros.programa_id) params.programa_id = filtros.programa_id;

        if (busqueda) {
            params.busqueda = busqueda;
        }

        router.get(reportesRoute(), params, {
            preserveState: true,
            replace: true,
        });
    }

    function formatearFecha(dateStr: string | null): string {
        if (!dateStr) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(dateStr));
    }

    const totalReportes = $derived(reportesData.total);
</script>

<AppHead title="Reportes" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Reportes"
        description="{totalReportes} reporte{totalReportes !== 1 ? 's' : ''} en total"
    >
        {#if puedeCrear}
            <Button asChild>
                {#snippet children(props)}
                    <Link href={reportesCreate()} {...props}>
                        <Plus class="mr-2 h-4 w-4" />
                        Crear Reporte
                    </Link>
                {/snippet}
            </Button>
        {/if}
    </PageHeader>

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Buscar por título o número..."
                        bind:value={busqueda}
                        onkeydown={(e) => { if (e.key === 'Enter') buscar(); }}
                        class="pl-9"
                    />
                </div>

                <Select
                    value={filtros.estado ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('estado', v)}
                    items={ESTADOS_FILTRO}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {#each ESTADOS_FILTRO as estado (estado.value)}
                            <SelectItem value={estado.value} label={estado.label}>
                                {estado.label}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>

                <Select
                    value={filtros.severidad ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('severidad', v)}
                    items={SEVERIDADES_FILTRO}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Severidad" />
                    </SelectTrigger>
                    <SelectContent>
                        {#each SEVERIDADES_FILTRO as severidad (severidad.value)}
                            <SelectItem value={severidad.value} label={severidad.label}>
                                {severidad.label}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>

                {#if programas.length > 0}
                    <Select
                        value={filtros.programa_id ?? 'todos'}
                        onValueChange={(v) => aplicarFiltro('programa_id', v)}
                        items={[{ value: 'todos', label: 'Todos los programas' }, ...programas.map((p) => ({ value: String(p.id), label: p.nombre }))]}
                    >
                        <SelectTrigger class="w-full sm:w-[200px]">
                            <SelectValue placeholder="Programa" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos" label="Todos los programas">Todos los programas</SelectItem>
                            {#each programas as programa (programa.id)}
                                <SelectItem value={String(programa.id)} label={programa.nombre}>
                                    {programa.nombre}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                {/if}
            </div>
        </CardContent>
    </Card>

    {#if reportesData.data.length === 0}
        <EmptyState
            icon={Bug}
            title="No se encontraron reportes"
            description="No hay reportes que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each reportesData.data as reporte (reporte.id)}
                <Link href={reportesShow(reporte.id)}>
                    <Card class="h-full transition-colors hover:border-primary">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle class="text-sm font-semibold leading-tight">
                                    {reporte.titulo}
                                </CardTitle>
                                <StateBadge estado={reporte.estado} />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {reporte.numero_reporte}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div class="flex items-center justify-between">
                                {#if reporte.severidad}
                                    <SeverityBadge severidad={reporte.severidad} />
                                {:else}
                                    <span class="text-xs text-muted-foreground">Sin severidad</span>
                                {/if}
                                {#if reporte.puntuacion_cvss}
                                    <span class="text-xs text-muted-foreground">
                                        CVSS {reporte.puntuacion_cvss}
                                    </span>
                                {/if}
                            </div>
                            {#if reporte.programa}
                                <p class="text-xs text-muted-foreground">
                                    Programa: {reporte.programa.nombre}
                                </p>
                            {/if}
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{reporte.investigador?.name ?? 'Desconocido'}</span>
                                <span>{formatearFecha(reporte.enviado_en ?? reporte.created_at)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            {/each}
        </div>

        {#if reportesData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each reportesData.links as link (link.label)}
                    {#if link.url}
                        <Link
                            href={link.url}
                            class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors hover:bg-secondary {link.active ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground'}"
                        >
                            {etiquetaPaginacion(link.label)}
                        </Link>
                    {:else}
                        <span class="inline-flex h-9 items-center justify-center px-3 text-sm text-muted-foreground">
                            {etiquetaPaginacion(link.label)}
                        </span>
                    {/if}
                {/each}
            </nav>
        {/if}
    {/if}
</div>
