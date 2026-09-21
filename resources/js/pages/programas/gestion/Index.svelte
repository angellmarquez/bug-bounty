<script module lang="ts">
    import { index as programasIndex, gestion as programasGestion } from '@/routes/programas';

    export const layout = {
        breadcrumbs: [
            { title: 'Programas', href: programasIndex() },
            { title: 'Gestion', href: programasGestion() },
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import Plus from '@lucide/svelte/icons/plus';
    import Shield from '@lucide/svelte/icons/shield';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import NivelAccesoBadge from '@/components/NivelAccesoBadge.svelte';
    import { Button } from '@/components/ui/button';
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
    import { index as programaRoute, gestion as gestionRoute, show as programaShow, create as programaCreate, edit as programaEdit } from '@/routes/programas';
    import type { Programa, ObjetivoPrograma } from '@/types/domain';
    import Edit from '@lucide/svelte/icons/edit';

    let {
        programas: programasData,
        filtros,
        esAdmin,
    }: {
        programas: {
            data: (Programa & { objetivos?: ObjetivoPrograma[]; reportes_count?: number; puede_editar?: boolean })[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            total: number;
        };
        filtros: { estado?: string; busqueda?: string };
        esAdmin: boolean;
    } = $props();

    const ESTADOS_FILTRO = [
        { value: 'todos', label: 'Todos los estados' },
        { value: 'borrador', label: 'Borrador' },
        { value: 'activo', label: 'Activo' },
        { value: 'en_pausa', label: 'En pausa' },
        { value: 'archivado', label: 'Archivado' },
    ];

    let busqueda = $state(filtros.busqueda ?? '');

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.busqueda) params.busqueda = filtros.busqueda;
        if (filtros.estado) params.estado = filtros.estado;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get(gestionRoute(), params, {
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

        router.get(gestionRoute(), params, {
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

<AppHead title="Gestion de Programas" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Gestion de Programas"
        description="{totalProgramas} programa{totalProgramas !== 1 ? 's' : ''}"
    >
        <Button asChild>
            {#snippet children(props)}
                <Link href={programaCreate()} {...props}>
                    <Plus class="mr-1 h-4 w-4" />
                    Crear Programa
                </Link>
            {/snippet}
        </Button>
    </PageHeader>

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

    {#if programasData.data.length === 0}
        <EmptyState
            icon={Shield}
            title="No se encontraron programas"
            description="Crea tu primer programa para comenzar a recibir reportes."
        >
        <Button asChild>
            {#snippet children(props)}
                <Link href={programaCreate()} {...props}>
                    <Plus class="mr-1 h-4 w-4" />
                    Crear Programa
                </Link>
            {/snippet}
        </Button>
        </EmptyState>
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

                            <div class="flex items-center justify-between text-xs text-muted-foreground mt-4">
                                <span>
                                    {formatearFecha(programa.inicia_en)} - {formatearFecha(programa.termina_en)}
                                </span>
                                <span>{programa.reportes_count ?? 0} reportes</span>
                            </div>
                            {#if programa.puede_editar}
                                <div class="mt-4 flex justify-end">
                                    <Button asChild variant="outline" size="sm">
                                        {#snippet children(props)}
                                            <Link href={programaEdit(programa.id)} {...props}>
                                                <Edit class="mr-1 h-3 w-3" />
                                                Editar
                                            </Link>
                                        {/snippet}
                                    </Button>
                                </div>
                            {/if}
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
