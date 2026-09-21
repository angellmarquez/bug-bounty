<script module lang="ts">
    import { index as programasIndex } from '@/routes/programas';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Programas',
                href: programasIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import Shield from '@lucide/svelte/icons/shield';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import NivelAccesoBadge from '@/components/NivelAccesoBadge.svelte';
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
    import { Badge } from '@/components/ui/badge';
    import { index as programaRoute, show as programaShow } from '@/routes/programas';
    import type { Programa } from '@/types/domain';

    let {
        programas: programasData,
        filtros,
        esGestion,
        esAdmin,
    }: {
        programas: {
            data: Programa[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        filtros: {
            estado?: string;
            busqueda?: string;
        };
        esGestion: boolean;
        esAdmin: boolean;
    } = $props();

    let busqueda = $state(filtros.busqueda ?? '');

    const estadosFiltro = $derived([
        { value: 'todos', label: 'Todos los estados' },
        { value: 'activo', label: 'Activo' },
        { value: 'en_pausa', label: 'En pausa' },
        ...(esGestion || esAdmin
            ? [
                  { value: 'borrador', label: 'Borrador' },
                  { value: 'archivado', label: 'Archivado' },
              ]
            : []),
    ]);

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.busqueda) params.busqueda = filtros.busqueda;
        if (filtros.estado) params.estado = filtros.estado;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get(programaRoute(), params, {
            preserveState: true,
            replace: true,
        });
    }

    function buscar() {
        const params: Record<string, string> = {};
        if (filtros.estado) params.estado = filtros.estado;

        if (busqueda) {
            params.busqueda = busqueda;
        }

        router.get(programaRoute(), params, {
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

    function tipoObjetivoLabel(tipo: string): string {
        const labels: Record<string, string> = {
            web: 'Web',
            api: 'API',
            movil: 'Movil',
            otro: 'Otro',
        };
        return labels[tipo] ?? tipo;
    }

    const totalProgramas = $derived(programasData.total);
</script>

<AppHead title="Programas" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Programas"
        description="{totalProgramas} programa{totalProgramas !== 1 ? 's' : ''} disponible{totalProgramas !== 1 ? 's' : ''}"
    />

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Buscar por nombre o descripcion..."
                        bind:value={busqueda}
                        onkeydown={(e) => { if (e.key === 'Enter') buscar(); }}
                        class="pl-9"
                    />
                </div>

                <Select
                    value={filtros.estado ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('estado', v)}
                    items={estadosFiltro}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        {#each estadosFiltro as estado (estado.value)}
                            <SelectItem value={estado.value} label={estado.label}>
                                {estado.label}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>

    {#if programasData.data.length === 0}
        <EmptyState
            icon={Shield}
            title="No se encontraron programas"
            description="No hay programas que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each programasData.data as programa (programa.id)}
                <Link href={programaShow(programa.id)}>
                    <Card class="h-full transition-colors hover:border-primary">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle class="text-sm font-semibold leading-tight">
                                    {programa.nombre}
                                </CardTitle>
                                <ProgramaStateBadge estado={programa.estado} />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p class="line-clamp-2 text-xs text-muted-foreground">
                                {programa.descripcion}
                            </p>

                            <div class="flex items-center gap-2">
                                <NivelAccesoBadge nivel={programa.nivel_acceso} />
                            </div>

                            {#if programa.objetivos && programa.objetivos.length > 0}
                                <div class="flex flex-wrap gap-1">
                                    {#each programa.objetivos as obj (obj.id)}
                                        <Badge variant="secondary" class="text-[10px]">
                                            {tipoObjetivoLabel(obj.tipo)}
                                        </Badge>
                                    {/each}
                                </div>
                            {/if}

                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    {formatearFecha(programa.inicia_en)} - {formatearFecha(programa.termina_en)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            {/each}
        </div>

        {#if programasData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each programasData.links as link (link.label)}
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
