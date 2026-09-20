<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Sanciones',
                href: '/admin/sanciones',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
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
    import { gravedadSancionColor, estadoSancionColor, gravedadSancionLabel, estadoSancionLabel } from '@/lib/status-colors';
    import type { Sancion } from '@/types/domain';

    const ESTADOS_FILTRO = [
        { value: 'todos', label: 'Todos los estados' },
        { value: 'aplicada', label: 'Aplicada' },
        { value: 'apelada', label: 'Apelada' },
        { value: 'revocada', label: 'Revocada' },
    ];

    const GRAVEDADES_FILTRO = [
        { value: 'todos', label: 'Todas las gravedades' },
        { value: 'leve', label: 'Leve' },
        { value: 'media', label: 'Media' },
        { value: 'grave', label: 'Grave' },
    ];

    let {
        sanciones: sancionesData,
        filtros,
    }: {
        sanciones: {
            data: Sancion[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        filtros: {
            estado?: string;
            gravedad?: string;
        };
    } = $props();

    let notaRevocacion = $state<Record<number, string>>({});

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.estado) params.estado = filtros.estado;
        if (filtros.gravedad) params.gravedad = filtros.gravedad;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/admin/sanciones', params, {
            preserveState: true,
            replace: true,
        });
    }

    function revocarSancion(sancionId: number) {
        const nota = notaRevocacion[sancionId] ?? '';
        router.post(`/admin/sanciones/${sancionId}/revocar`, {
            nota,
        }, {
            preserveState: true,
            onSuccess: () => {
                notaRevocacion[sancionId] = '';
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

    const totalSanciones = $derived(sancionesData.total);
</script>

<AppHead title="Sanciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Sanciones"
        description="{totalSanciones} sancion{totalSanciones !== 1 ? 'es' : ''} registrada{totalSanciones !== 1 ? 's' : ''}"
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

                <Select
                    value={filtros.gravedad ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('gravedad', v)}
                    items={GRAVEDADES_FILTRO}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Gravedad" />
                    </SelectTrigger>
                    <SelectContent>
                        {#each GRAVEDADES_FILTRO as gravedad (gravedad.value)}
                            <SelectItem value={gravedad.value} label={gravedad.label}>
                                {gravedad.label}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>

    {#if sancionesData.data.length === 0}
        <EmptyState
            icon={ShieldAlert}
            title="No se encontraron sanciones"
            description="No hay sanciones que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each sancionesData.data as sancion (sancion.id)}
                <Card class="h-full">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-sm font-semibold leading-tight">
                                {sancion.usuario?.name ?? 'Desconocido'}
                            </CardTitle>
                            <div class="flex gap-1">
                                <span class={gravedadSancionColor(sancion.gravedad)}>
                                    {gravedadSancionLabel(sancion.gravedad)}
                                </span>
                                <span class={estadoSancionColor(sancion.estado)}>
                                    {estadoSancionLabel(sancion.estado)}
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">{sancion.motivo}</p>

                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Puntos: <span class="font-semibold text-destructive">-{sancion.puntos}</span></span>
                            <span>{formatearFecha(sancion.created_at)}</span>
                        </div>

                        {#if sancion.suspension_desde && sancion.suspension_hasta}
                            <div class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                Suspension: {formatearFecha(sancion.suspension_desde)} - {formatearFecha(sancion.suspension_hasta)}
                            </div>
                        {/if}

                        {#if sancion.estado === 'aplicada'}
                            <div class="space-y-2 border-t border-border pt-3">
                                <Input
                                    type="text"
                                    placeholder="Nota de revocacion..."
                                    bind:value={notaRevocacion[sancion.id]}
                                />
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    class="w-full"
                                    onclick={() => revocarSancion(sancion.id)}
                                >
                                    Revocar
                                </Button>
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            {/each}
        </div>

        {#if sancionesData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each sancionesData.links as link (link.label)}
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
