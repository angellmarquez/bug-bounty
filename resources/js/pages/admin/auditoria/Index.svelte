<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Auditoria',
                href: '/admin/auditoria',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
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
    import type { Auditoria } from '@/types/domain';
    import type { User } from '@/types/auth';

    let {
        auditoria: auditoriaData,
        usuarios,
        filtros,
    }: {
        auditoria: {
            data: Auditoria[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        usuarios: User[];
        filtros: {
            accion?: string;
            usuario_id?: string;
            entidad?: string;
        };
    } = $props();

    let accionBusqueda = $state(filtros.accion ?? '');

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.accion) params.accion = filtros.accion;
        if (filtros.usuario_id) params.usuario_id = filtros.usuario_id;
        if (filtros.entidad) params.entidad = filtros.entidad;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/admin/auditoria', params, {
            preserveState: true,
            replace: true,
        });
    }

    function buscar() {
        const params: Record<string, string> = {};
        if (filtros.usuario_id) params.usuario_id = filtros.usuario_id;
        if (filtros.entidad) params.entidad = filtros.entidad;

        if (accionBusqueda) {
            params.accion = accionBusqueda;
        }

        router.get('/admin/auditoria', params, {
            preserveState: true,
            replace: true,
        });
    }

    function formatearFecha(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }

    const entidades = ['Reporte', 'Programa', 'Usuario', 'Sancion', 'Apelacion', 'ClavePgp'];

    const totalEntradas = $derived(auditoriaData.total);
</script>

<AppHead title="Auditoria" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Auditoria"
        description="{totalEntradas} entrada{totalEntradas !== 1 ? 's' : ''} de registro"
    />

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Input
                        type="text"
                        placeholder="Buscar por accion..."
                        bind:value={accionBusqueda}
                        onkeydown={(e) => { if (e.key === 'Enter') buscar(); }}
                    />
                </div>

                <Select
                    value={filtros.usuario_id ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('usuario_id', v)}
                    items={[{ value: 'todos', label: 'Todos los usuarios' }, ...usuarios.map((u) => ({ value: String(u.id), label: u.name }))]}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Usuario" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos" label="Todos los usuarios">Todos los usuarios</SelectItem>
                        {#each usuarios as usuario (usuario.id)}
                            <SelectItem value={String(usuario.id)} label={usuario.name}>
                                {usuario.name}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>

                <Select
                    value={filtros.entidad ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('entidad', v)}
                    items={[{ value: 'todos', label: 'Todas las entidades' }, ...entidades.map((e) => ({ value: e, label: e }))]}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Entidad" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos" label="Todas las entidades">Todas las entidades</SelectItem>
                        {#each entidades as entidad (entidad)}
                            <SelectItem value={entidad} label={entidad}>
                                {entidad}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>

    {#if auditoriaData.data.length === 0}
        <EmptyState
            icon={ClipboardList}
            title="No se encontraron registros"
            description="No hay entradas de auditoria que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="space-y-3">
            {#each auditoriaData.data as entrada (entrada.id)}
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                                    {entrada.usuario?.name?.charAt(0) ?? '?'}
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        {entrada.usuario?.name ?? 'Sistema'}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {entrada.accion}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span class="inline-flex items-center rounded-md border border-secondary bg-secondary px-2 py-0.5 font-semibold text-secondary-foreground">
                                    {entrada.entidad_type}
                                </span>
                                <span>#{entrada.entidad_id}</span>
                                <span>{formatearFecha(entrada.created_at)}</span>
                            </div>
                        </div>
                        {#if entrada.metadata && Object.keys(entrada.metadata).length > 0}
                            <div class="mt-3 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                {JSON.stringify(entrada.metadata)}
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            {/each}
        </div>

        {#if auditoriaData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each auditoriaData.links as link (link.label)}
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
