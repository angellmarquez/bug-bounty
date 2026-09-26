<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
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
    import Building2 from '@lucide/svelte/icons/building-2';
    import Search from '@lucide/svelte/icons/search';
    import Users from '@lucide/svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import { etiquetaPaginacion } from '@/lib/paginacion';

    type TipoCuenta = 'administrador' | 'moderador' | 'empresa' | 'investigador';
    type EstadoCuenta = 'activa' | 'suspendida' | 'desactivada';

    interface FichaUsuario {
        id: number;
        name: string;
        email: string;
        created_at: string | null;
        tipo: TipoCuenta | null;
        empresa: string | null;
        reputation_score: number | null;
        estado: EstadoCuenta;
        suspendido_hasta: string | null;
    }

    let {
        usuarios: usuariosData,
        filtros,
    }: {
        usuarios: {
            data: FichaUsuario[];
            links: { url: string | null; label: string; active: boolean }[];
            last_page: number;
            total: number;
        };
        filtros: {
            tipo: TipoCuenta | null;
            estado: EstadoCuenta | null;
            busqueda: string | null;
        };
    } = $props();

    const tipos: { value: TipoCuenta; label: string; clase: string }[] = [
        { value: 'administrador', label: 'Administrador', clase: 'border-chart-5/40 bg-chart-5/10 text-chart-5' },
        { value: 'moderador', label: 'Moderador', clase: 'border-chart-2/40 bg-chart-2/10 text-chart-2' },
        { value: 'empresa', label: 'Empresa', clase: 'border-chart-4/40 bg-chart-4/10 text-chart-4' },
        { value: 'investigador', label: 'Investigador', clase: 'border-primary/40 bg-primary/10 text-primary' },
    ];

    const estados: { value: EstadoCuenta; label: string; clase: string }[] = [
        { value: 'activa', label: 'Activa', clase: 'border-chart-1/40 bg-chart-1/10 text-chart-1' },
        { value: 'suspendida', label: 'Suspendida', clase: 'border-destructive/40 bg-destructive/10 text-destructive' },
        { value: 'desactivada', label: 'Desactivada', clase: 'border-border bg-muted text-muted-foreground' },
    ];

    // Solo es el valor inicial del buscador: luego lo controla el input.
    // svelte-ignore state_referenced_locally
    let busqueda = $state(filtros.busqueda ?? '');

    function filtrar(cambios: Partial<Record<'tipo' | 'estado' | 'busqueda', string | null>>) {
        const params: Record<string, string> = {};
        const actuales = { ...filtros, ...cambios };

        for (const [clave, valor] of Object.entries(actuales)) {
            if (valor && valor !== 'todos') {
                params[clave] = valor;
            }
        }

        router.get('/admin/usuarios', params, { preserveState: true, replace: true });
    }

    function fecha(iso: string | null): string {
        return iso ? new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
    }

    const totalUsuarios = $derived(usuariosData.total);
</script>

<AppHead title="Usuarios" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Usuarios"
        description="{totalUsuarios} usuario{totalUsuarios !== 1 ? 's' : ''} registrado{totalUsuarios !== 1 ? 's' : ''} · consulta el tipo y el estado de cada cuenta"
    />

    <Card>
        <CardContent>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Buscar por nombre o email..."
                        bind:value={busqueda}
                        onkeydown={(e) => { if (e.key === 'Enter') filtrar({ busqueda }); }}
                        class="pl-9"
                    />
                </div>

                <Select
                    value={filtros.tipo ?? 'todos'}
                    onValueChange={(v) => filtrar({ tipo: v })}
                    items={[{ value: 'todos', label: 'Todos los tipos' }, ...tipos]}
                >
                    <SelectTrigger class="w-full sm:w-[180px]" aria-label="Tipo de cuenta">
                        <SelectValue placeholder="Tipo" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos" label="Todos los tipos">Todos los tipos</SelectItem>
                        {#each tipos as tipo (tipo.value)}
                            <SelectItem value={tipo.value} label={tipo.label}>{tipo.label}</SelectItem>
                        {/each}
                    </SelectContent>
                </Select>

                <Select
                    value={filtros.estado ?? 'todos'}
                    onValueChange={(v) => filtrar({ estado: v })}
                    items={[{ value: 'todos', label: 'Todos los estados' }, ...estados]}
                >
                    <SelectTrigger class="w-full sm:w-[180px]" aria-label="Estado de la cuenta">
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos" label="Todos los estados">Todos los estados</SelectItem>
                        {#each estados as estado (estado.value)}
                            <SelectItem value={estado.value} label={estado.label}>{estado.label}</SelectItem>
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
                {@const tipo = tipos.find((t) => t.value === usuario.tipo)}
                {@const estado = estados.find((e) => e.value === usuario.estado)}
                <Card class="h-full">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-sm font-semibold leading-tight">
                                {usuario.name}
                            </CardTitle>
                            {#if estado}
                                <span class="shrink-0 rounded-md border px-2 py-0.5 text-xs font-semibold {estado.clase}" data-estado={estado.value}>
                                    {estado.label}
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground">{usuario.email}</p>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex flex-wrap items-center gap-2">
                            {#if tipo}
                                <span class="rounded-md border px-2 py-0.5 font-semibold {tipo.clase}" data-tipo={tipo.value}>
                                    {tipo.label}
                                </span>
                            {:else}
                                <span class="rounded-md border border-border bg-muted px-2 py-0.5 text-muted-foreground">Sin rol</span>
                            {/if}
                            {#if usuario.reputation_score !== null}
                                <RangoBadge puntos={usuario.reputation_score} mostrarPuntos />
                            {/if}
                        </div>

                        {#if usuario.empresa}
                            <p class="flex items-center gap-1.5 text-muted-foreground">
                                <Building2 class="size-3.5" aria-hidden="true" />
                                {usuario.empresa}
                            </p>
                        {/if}

                        {#if usuario.estado === 'suspendida' && usuario.suspendido_hasta}
                            <p class="text-destructive">Suspendida hasta el {fecha(usuario.suspendido_hasta)}</p>
                        {/if}

                        {#if usuario.created_at}
                            <p class="text-muted-foreground">Registrado el {fecha(usuario.created_at)}</p>
                        {/if}
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
