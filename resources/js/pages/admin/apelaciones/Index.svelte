<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
            },
            {
                title: 'Apelaciones',
                href: '/admin/apelaciones',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import { Input } from '@/components/ui/input';
    import { Button } from '@/components/ui/button';
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
    import { estadoApelacionColor, estadoApelacionLabel, estadoSancionColor, estadoSancionLabel, gravedadSancionColor, gravedadSancionLabel } from '@/lib/status-colors';
    import type { Apelacion } from '@/types/domain';

    const ESTADOS_FILTRO = [
        { value: 'todos', label: 'Todos los estados' },
        { value: 'pendiente', label: 'Pendiente' },
        { value: 'aprobada', label: 'Aprobada' },
        { value: 'rechazada', label: 'Rechazada' },
    ];

    let {
        apelaciones: apelacionesData,
        filtros,
    }: {
        apelaciones: {
            data: Apelacion[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        filtros: {
            estado?: string;
        };
    } = $props();

    let notasResolucion = $state<Record<number, string>>({});
    let erroresNota = $state<Record<number, string>>({});

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.estado) params.estado = filtros.estado;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/admin/apelaciones', params, {
            preserveState: true,
            replace: true,
        });
    }

    function resolverApelacion(apelacionId: number, aprobada: boolean) {
        const nota = (notasResolucion[apelacionId] ?? '').trim();
        if (!nota) {
            erroresNota[apelacionId] = 'Escribe una nota explicando la decisión.';
            return;
        }
        erroresNota[apelacionId] = '';
        router.post(`/admin/apelaciones/${apelacionId}/resolver`, {
            aprobada,
            nota,
        }, {
            preserveState: true,
            onSuccess: () => {
                notasResolucion[apelacionId] = '';
            },
            onError: (errores) => {
                erroresNota[apelacionId] = errores.nota ?? 'No se pudo resolver la apelación.';
            },
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

    const totalApelaciones = $derived(apelacionesData.total);
</script>

<AppHead title="Apelaciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Apelaciones"
        description="{totalApelaciones} apelacion{totalApelaciones !== 1 ? 'es' : ''} registrada{totalApelaciones !== 1 ? 's' : ''}"
    />

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
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
            </div>
        </CardContent>
    </Card>

    {#if apelacionesData.data.length === 0}
        <EmptyState
            icon={MessageSquare}
            title="No se encontraron apelaciones"
            description="No hay apelaciones que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each apelacionesData.data as apelacion (apelacion.id)}
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

                        {#if apelacion.sancion}
                            <div class="rounded-md bg-muted/50 px-3 py-2 text-xs space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground">Sancion:</span>
                                    <span class={gravedadSancionColor(apelacion.sancion.gravedad)}>
                                        {gravedadSancionLabel(apelacion.sancion.gravedad)}
                                    </span>
                                    <span class={estadoSancionColor(apelacion.sancion.estado)}>
                                        {estadoSancionLabel(apelacion.sancion.estado)}
                                    </span>
                                </div>
                                <p class="text-muted-foreground">{apelacion.sancion.motivo}</p>
                            </div>
                        {/if}

                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{formatearFecha(apelacion.created_at)}</span>
                            {#if apelacion.nota_resolucion}
                                <span>Resuelta: {formatearFecha(apelacion.resuelta_en)}</span>
                            {/if}
                        </div>

                        {#if apelacion.nota_resolucion}
                            <div class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                Resolucion: {apelacion.nota_resolucion}
                            </div>
                        {/if}

                        {#if apelacion.estado === 'pendiente'}
                            <div class="space-y-2 border-t border-border pt-3">
                                <Input
                                    type="text"
                                    placeholder="Nota de resolución (obligatoria)..."
                                    bind:value={notasResolucion[apelacion.id]}
                                    aria-invalid={erroresNota[apelacion.id] ? 'true' : undefined}
                                />
                                {#if erroresNota[apelacion.id]}
                                    <p class="text-xs text-destructive" role="alert">{erroresNota[apelacion.id]}</p>
                                {/if}
                                <div class="flex gap-2">
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="flex-1 bg-chart-1 text-white hover:bg-chart-1/90"
                                        onclick={() => resolverApelacion(apelacion.id, true)}
                                    >
                                        Aprobar
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        class="flex-1"
                                        onclick={() => resolverApelacion(apelacion.id, false)}
                                    >
                                        Rechazar
                                    </Button>
                                </div>
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            {/each}
        </div>

        {#if apelacionesData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each apelacionesData.links as link (link.label)}
                    {#if link.url}
                        <a
                            href={link.url}
                            class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors hover:bg-secondary {link.active ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground'}"
                        >
                            {@html link.label}
                        </a>
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
