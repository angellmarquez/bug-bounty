<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Apelaciones', href: '/moderacion/apelaciones' },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ResolverApelacionForm from '@/components/ResolverApelacionForm.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import {
        estadoApelacionColor,
        estadoApelacionLabel,
        estadoSancionColor,
        estadoSancionLabel,
        gravedadSancionColor,
        gravedadSancionLabel,
    } from '@/lib/status-colors';
    import type { ApelacionParaResolver } from '@/types/domain';
    import { etiquetaPaginacion } from '@/lib/paginacion';

    const ESTADOS_FILTRO = [
        { value: 'todos', label: 'Todos los estados' },
        { value: 'pendiente', label: 'Pendiente' },
        { value: 'aprobada', label: 'Aprobada' },
        { value: 'rechazada', label: 'Rechazada' },
    ];

    let {
        apelaciones,
        filtros,
    }: {
        apelaciones: {
            data: ApelacionParaResolver[];
            links: { url: string | null; label: string; active: boolean }[];
            last_page: number;
            total: number;
        };
        filtros: { estado?: string };
    } = $props();

    function aplicarFiltro(value: string | null) {
        const params: Record<string, string> = {};
        if (value && value !== 'todos') params.estado = value;
        router.get('/moderacion/apelaciones', params, { preserveState: true, replace: true });
    }

    function fecha(dateStr: string | null): string {
        if (!dateStr) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateStr));
    }

    const pendientes = $derived(apelaciones.data.filter((a) => a.estado === 'pendiente').length);
</script>

<AppHead title="Apelaciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Apelaciones"
        description="{apelaciones.total} apelación{apelaciones.total !== 1 ? 'es' : ''} · {pendientes} pendiente{pendientes !== 1 ? 's' : ''} en esta página. Las resuelve exclusivamente el administrador para garantizar máxima seguridad e imparcialidad."
    />

    <Card>
        <CardContent>
            <Select value={filtros.estado ?? 'todos'} onValueChange={aplicarFiltro} items={ESTADOS_FILTRO}>
                <SelectTrigger class="w-full sm:w-[220px]">
                    <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                    {#each ESTADOS_FILTRO as estado (estado.value)}
                        <SelectItem value={estado.value} label={estado.label}>{estado.label}</SelectItem>
                    {/each}
                </SelectContent>
            </Select>
        </CardContent>
    </Card>

    {#if apelaciones.data.length === 0}
        <EmptyState
            icon={MessageSquare}
            title="No se encontraron apelaciones"
            description="No hay apelaciones que coincidan con el filtro seleccionado."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each apelaciones.data as apelacion (apelacion.id)}
                <div class="h-full" data-test="apelacion-card">
                <Card class="h-full">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-sm font-semibold leading-tight">
                                {apelacion.usuario?.name ?? 'Desconocido'}
                            </CardTitle>
                            <span class={estadoApelacionColor(apelacion.estado)}>
                                {estadoApelacionLabel(apelacion.estado)}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">{apelacion.motivo}</p>

                        <div class="space-y-1 rounded-md bg-muted/50 px-3 py-2 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-muted-foreground">Sanción:</span>
                                <span class={gravedadSancionColor(apelacion.sancion.gravedad)}>
                                    {gravedadSancionLabel(apelacion.sancion.gravedad)}
                                </span>
                                <span class={estadoSancionColor(apelacion.sancion.estado)}>
                                    {estadoSancionLabel(apelacion.sancion.estado)}
                                </span>
                            </div>
                            <p class="text-muted-foreground">{apelacion.sancion.motivo}</p>
                            <p data-test="sanciono">
                                Aplicada por:
                                <span class="font-medium">
                                    {apelacion.sancion.aplicada_por?.name ?? 'Sistema (auditoría automática)'}
                                </span>
                            </p>
                        </div>

                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{fecha(apelacion.created_at)}</span>
                            {#if apelacion.resuelta_por}
                                <span>Resuelta por {apelacion.resuelta_por.name}</span>
                            {/if}
                        </div>

                        {#if apelacion.nota_resolucion}
                            <div class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                Resolución: {apelacion.nota_resolucion}
                            </div>
                        {/if}

                        <Link href="/moderacion/apelaciones/{apelacion.id}" class="text-xs text-primary hover:underline" data-test="ver-apelacion">
                            Ver detalle y registro de control
                        </Link>

                        {#if apelacion.estado === 'pendiente'}
                            {#if apelacion.puede_resolver}
                                <ResolverApelacionForm apelacionId={apelacion.id} />
                            {:else}
                                <p class="rounded-md border border-aviso/40 bg-aviso/10 px-3 py-2 text-xs" data-test="bloqueo-resolver">
                                    {apelacion.motivo_bloqueo}
                                </p>
                            {/if}
                        {/if}
                    </CardContent>
                </Card>
                </div>
            {/each}
        </div>

        {#if apelaciones.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each apelaciones.links as link (link.label)}
                    {#if link.url}
                        <a
                            href={link.url}
                            class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors hover:bg-secondary {link.active ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground'}"
                        >
                            {etiquetaPaginacion(link.label)}
                        </a>
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
