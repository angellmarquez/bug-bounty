<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Usuarios',
                href: '/admin/usuarios',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import Users from '@lucide/svelte/icons/users';
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
    import type { User } from '@/types/auth';
    import type { Rol } from '@/types/domain';

    let {
        usuarios: usuariosData,
        roles,
        filtros,
    }: {
        usuarios: {
            data: User[];
            links: { url: string | null; label: string; active: boolean }[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        roles: Rol[];
        filtros: {
            busqueda?: string;
            rol?: string;
        };
    } = $props();

    let busqueda = $state(filtros.busqueda ?? '');
    let rolActivo = $state(filtros.rol ?? '');

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.busqueda) params.busqueda = filtros.busqueda;
        if (filtros.rol) params.rol = filtros.rol;

        if (value && value !== 'todos') {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/admin/usuarios', params, {
            preserveState: true,
            replace: true,
        });
    }

    function buscar() {
        const params: Record<string, string> = {};
        if (filtros.rol) params.rol = filtros.rol;

        if (busqueda) {
            params.busqueda = busqueda;
        }

        router.get('/admin/usuarios', params, {
            preserveState: true,
            replace: true,
        });
    }

    function cambiarRol(usuarioId: number, nuevoRol: string) {
        router.put(`/admin/usuarios/${usuarioId}`, {
            rol: nuevoRol,
        }, {
            preserveState: true,
        });
    }

    const totalUsuarios = $derived(usuariosData.total);
</script>

<AppHead title="Usuarios" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Usuarios"
        description="{totalUsuarios} usuario{totalUsuarios !== 1 ? 's' : ''} registrado{totalUsuarios !== 1 ? 's' : ''}"
    />

    <Card>
        <CardContent class="pt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Buscar por nombre o email..."
                        bind:value={busqueda}
                        onkeydown={(e) => { if (e.key === 'Enter') buscar(); }}
                        class="pl-9"
                    />
                </div>

                <Select
                    value={filtros.rol ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('rol', v)}
                    items={[{ value: 'todos', label: 'Todos los roles' }, ...roles.map((r) => ({ value: r.slug, label: r.nombre }))]}
                >
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos" label="Todos los roles">Todos los roles</SelectItem>
                        {#each roles as rol (rol.id)}
                            <SelectItem value={rol.slug} label={rol.nombre}>
                                {rol.nombre}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>

    {#if usuariosData.data.length === 0}
        <EmptyState
            icon={Users}
            title="No se encontraron usuarios"
            description="No hay usuarios que coincidan con los filtros seleccionados."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each usuariosData.data as usuario (usuario.id)}
                <Card class="h-full">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-sm font-semibold leading-tight">
                                {usuario.name}
                            </CardTitle>
                            <span class="inline-flex items-center rounded-md border border-transparent bg-chart-1 px-2 py-0.5 text-xs font-semibold text-white">
                                {usuario.reputation_score} pts
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {usuario.email}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">Rol:</span>
                            <Select
                                value={usuario.roles[0] ?? 'investigador'}
                                onValueChange={(v) => cambiarRol(usuario.id, v)}
                                items={roles.map((r) => ({ value: r.slug, label: r.nombre }))}
                            >
                                <SelectTrigger class="h-8 w-full text-xs">
                                    <SelectValue placeholder={usuario.roles[0] ?? 'investigador'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {#each roles as rol (rol.id)}
                                        <SelectItem value={rol.slug} label={rol.nombre}>
                                            {rol.nombre}
                                        </SelectItem>
                                    {/each}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>

        {#if usuariosData.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each usuariosData.links as link (link.label)}
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
