<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
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
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Bug from '@lucide/svelte/icons/bug';
    import Shield from '@lucide/svelte/icons/shield';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Users from '@lucide/svelte/icons/users';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import Settings from '@lucide/svelte/icons/settings';
    import Key from '@lucide/svelte/icons/key';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import CircleHelp from '@lucide/svelte/icons/circle-help';
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
    } from '@/components/ui/card';
    import type { Auditoria } from '@/types/domain';
    import { ROLES, ORDEN_ROLES, rolPrincipal } from '@/lib/roles';
    import {
        CATEGORIAS,
        categoriaDe,
        etiquetaAccion,
        diferenciasDeConfig,
        formatearValorConfig,
        type CategoriaAuditoria,
    } from '@/lib/auditoria';
    import { etiquetaPaginacion } from '@/lib/paginacion';

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
        usuarios: { id: number; name: string; email: string }[];
        filtros: {
            accion?: string;
            usuario_id?: string;
            entidad?: string;
            rol?: string;
        };
    } = $props();

    let accionBusqueda = $state(filtros.accion ?? '');
    let abiertos = $state<Record<number, boolean>>({});

    const ICONOS: Record<CategoriaAuditoria, typeof Bug> = {
        reportes: Bug,
        programas: Shield,
        empresas: Building2,
        usuarios: Users,
        moderadores: ShieldCheck,
        sanciones: ShieldAlert,
        apelaciones: MessageSquare,
        config: Settings,
        pgp: Key,
        seguridad: AlertTriangle,
        otro: CircleHelp,
    };

    const ROLES_FILTRO: { value: string; label: string }[] = [
        { value: 'todos', label: 'Todos los roles' },
        ...ORDEN_ROLES.map((rol) => ({ value: rol, label: ROLES[rol].etiqueta })),
        { value: 'sistema', label: 'Sistema' },
    ];

    function aplicarFiltro(key: string, value: string | null) {
        const params: Record<string, string> = {};
        if (filtros.accion) params.accion = filtros.accion;
        if (filtros.usuario_id) params.usuario_id = filtros.usuario_id;
        if (filtros.entidad) params.entidad = filtros.entidad;
        if (filtros.rol) params.rol = filtros.rol;

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
        if (filtros.rol) params.rol = filtros.rol;

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

    function humanizarClave(clave: string): string {
        return clave.replace(/_/g, ' ').replace(/\b\w/g, (letra) => letra.toUpperCase());
    }

    /** Todo lo del detalle excepto estado_anterior/estado_nuevo (esos se muestran juntos como "A → B"). */
    function restoDelDetalle(detalle: Record<string, unknown>): [string, unknown][] {
        return Object.entries(detalle).filter(([clave]) => clave !== 'estado_anterior' && clave !== 'estado_nuevo');
    }

    const entidades = ['Reporte', 'Programa', 'ObjetivoPrograma', 'Usuario', 'Sancion', 'Apelacion', 'Empresa', 'ClavePgp'];

    const totalEntradas = $derived(auditoriaData.total);
</script>

<AppHead title="Auditoria" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Auditoria"
        description="{totalEntradas} entrada{totalEntradas !== 1 ? 's' : ''} — todo lo que hacen investigadores, moderadores y empresas en la plataforma"
    />

    <Card>
        <CardContent>
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
                    value={filtros.rol ?? 'todos'}
                    onValueChange={(v) => aplicarFiltro('rol', v)}
                    items={ROLES_FILTRO}
                >
                    <SelectTrigger class="w-full sm:w-[160px]">
                        <SelectValue placeholder="Rol" />
                    </SelectTrigger>
                    <SelectContent>
                        {#each ROLES_FILTRO as opcion (opcion.value)}
                            <SelectItem value={opcion.value} label={opcion.label}>
                                {opcion.label}
                            </SelectItem>
                        {/each}
                    </SelectContent>
                </Select>

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
                {@const categoria = categoriaDe(entrada.accion)}
                {@const Icono = ICONOS[categoria]}
                {@const rol = rolPrincipal(entrada.usuario?.roles)}
                {@const esConfig = entrada.accion === 'admin.config.reputacion_actualizada' && entrada.detalle?.config_anterior && entrada.detalle?.config_nueva}
                {@const cambios = esConfig ? diferenciasDeConfig(entrada.detalle!.config_anterior, entrada.detalle!.config_nueva) : []}
                {@const resto = entrada.detalle && !esConfig ? restoDelDetalle(entrada.detalle) : []}
                {@const tieneMasDetalle = esConfig ? cambios.length > 0 : resto.length > 0}
                <Card>
                    <CardContent>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {CATEGORIAS[categoria].clase}">
                                    <Icono class="h-4 w-4" />
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm font-medium">{etiquetaAccion(entrada.accion)}</p>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span>{entrada.usuario?.name ?? 'Sistema'}</span>
                                        {#if rol}
                                            <span class="inline-flex items-center rounded-md border px-1.5 py-0.5 font-semibold {ROLES[rol].clase}">
                                                {ROLES[rol].etiqueta}
                                            </span>
                                        {:else if !entrada.usuario}
                                            <span class="inline-flex items-center rounded-md border border-muted-foreground/30 bg-muted px-1.5 py-0.5 font-semibold text-muted-foreground">
                                                Sistema
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="font-mono text-[11px] text-muted-foreground/70">{entrada.accion}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-start gap-1 text-xs text-muted-foreground sm:items-end">
                                {#if entrada.entidad_type}
                                    <span class="inline-flex items-center rounded-md border border-secondary bg-secondary px-2 py-0.5 font-semibold text-secondary-foreground">
                                        {entrada.entidad_type} #{entrada.entidad_id}
                                    </span>
                                {/if}
                                <span>{formatearFecha(entrada.created_at)}</span>
                                {#if entrada.ip}
                                    <span class="font-mono">{entrada.ip}</span>
                                {/if}
                            </div>
                        </div>

                        {#if tieneMasDetalle}
                            <button
                                type="button"
                                class="mt-3 flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                onclick={() => (abiertos[entrada.id] = !abiertos[entrada.id])}
                            >
                                <ChevronDown class="h-3 w-3 transition-transform {abiertos[entrada.id] ? 'rotate-180' : ''}" />
                                {abiertos[entrada.id] ? 'Ocultar detalle' : 'Ver detalle'}
                            </button>

                            {#if abiertos[entrada.id]}
                                <div class="mt-2 space-y-1.5 rounded-md bg-muted/50 px-3 py-2.5 text-xs">
                                    {#if esConfig}
                                        {#each cambios as cambio (cambio.ruta)}
                                            <p>
                                                <span class="font-medium text-foreground">{cambio.ruta}:</span>
                                                <span class="text-muted-foreground">{formatearValorConfig(cambio.antes)}</span>
                                                <span class="text-muted-foreground"> → </span>
                                                <span class="font-medium text-chart-1">{formatearValorConfig(cambio.despues)}</span>
                                            </p>
                                        {/each}
                                    {:else}
                                        {#if entrada.detalle?.estado_anterior && entrada.detalle?.estado_nuevo}
                                            <p>
                                                <span class="font-medium text-foreground">Estado:</span>
                                                <span class="text-muted-foreground">{formatearValorConfig(entrada.detalle.estado_anterior)}</span>
                                                <span class="text-muted-foreground"> → </span>
                                                <span class="font-medium text-chart-1">{formatearValorConfig(entrada.detalle.estado_nuevo)}</span>
                                            </p>
                                        {/if}
                                        {#each resto as [clave, valor] (clave)}
                                            <p>
                                                <span class="font-medium text-foreground">{humanizarClave(clave)}:</span>
                                                <span class="text-muted-foreground">
                                                    {typeof valor === 'object' && valor !== null ? JSON.stringify(valor) : formatearValorConfig(valor)}
                                                </span>
                                            </p>
                                        {/each}
                                    {/if}
                                </div>
                            {/if}
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
