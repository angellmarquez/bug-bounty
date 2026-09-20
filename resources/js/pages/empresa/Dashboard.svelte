<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Empresa', href: '/empresa' }],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Link } from '@inertiajs/svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Settings from '@lucide/svelte/icons/settings';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import type { EstadoPrograma, EstadoReporte, Severidad } from '@/types/enums';

    let { empresa }: {
        empresa: {
            id: number;
            razon_social: string;
            nombre_comercial: string | null;
            identificador_fiscal: string;
            email: string;
            estado: string;
            motivo_estado: string | null;
            rol_interno: string;
            puedeOperar: boolean;
            usuarios: { id: number; name: string; email: string }[];
            invitaciones: { id: number; email: string; expira_en: string }[];
            programas: {
                id: number;
                nombre: string;
                estado: EstadoPrograma;
                es_publico: boolean;
                reportes_total: number;
                reportes_pendientes: number;
                reportes_aprobados: number;
                reportes_rechazados: number;
            }[];
            resumen: {
                programas: number;
                reportes: number;
                pendientes: number;
                aprobados: number;
                rechazados: number;
            };
            reportes: {
                id: number;
                numero_reporte: string;
                titulo: string;
                estado: EstadoReporte;
                severidad: Severidad | null;
                categoria: string | null;
                vector_cvss: string | null;
                puntuacion_cvss: number | null;
                programa_id: number;
                programa_nombre: string;
                investigador: { id: number; name: string } | null;
                aprobado: boolean;
                aprobado_por: string | null;
                aprobado_en: string | null;
                descripcion: string | null;
                poc: Record<string, unknown> | null;
                cifrado_indisponible: boolean;
                created_at: string | null;
            }[];
        };
    } = $props();

    type FiltroReportes = 'todos' | 'pendientes' | 'aprobados' | 'rechazados';

    const ESTADOS_PENDIENTES: EstadoReporte[] = ['enviado', 'en_revision'];
    const ESTADOS_RECHAZADOS: EstadoReporte[] = ['rechazado', 'duplicado', 'fuera_de_alcance'];

    const filtros: { valor: FiltroReportes; etiqueta: string }[] = [
        { valor: 'todos', etiqueta: 'Todos' },
        { valor: 'pendientes', etiqueta: 'Pendientes de moderación' },
        { valor: 'aprobados', etiqueta: 'Aprobados por moderadores' },
        { valor: 'rechazados', etiqueta: 'Rechazados' },
    ];

    let filtroEstado = $state<FiltroReportes>('todos');
    let filtroPrograma = $state('');
    let informeAbierto = $state<number | null>(null);

    const reportesFiltrados = $derived(
        empresa.reportes.filter((reporte) => {
            if (filtroPrograma && String(reporte.programa_id) !== filtroPrograma) return false;
            if (filtroEstado === 'pendientes') return ESTADOS_PENDIENTES.includes(reporte.estado);
            if (filtroEstado === 'aprobados') return reporte.aprobado;
            if (filtroEstado === 'rechazados') return ESTADOS_RECHAZADOS.includes(reporte.estado);
            return true;
        }),
    );

    function cambiarEstadoPrograma(programaId: number, estado: 'activo' | 'en_pausa' | 'archivado') {
        if (estado === 'activo' && !confirm('¿Publicar este programa? Los investigadores podrán verlo y enviar reportes.')) return;
        router.post(`/programas/${programaId}/cambiar-estado`, { estado }, { preserveScroll: true });
    }

    function formatearFecha(fecha: string | null): string {
        if (!fecha) return '';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(fecha));
    }

    let emailMiembro = $state('');
    let emailInvitacion = $state('');
    let invitacionUrl = $state('');

    function agregarMiembro() {
        router.post('/empresa/miembros', { email: emailMiembro }, {
            preserveState: true,
            onSuccess: () => { emailMiembro = ''; },
        });
    }

    function eliminarMiembro(userId: number) {
        router.delete(`/empresa/miembros/${userId}`, { preserveState: true });
    }

    function invitarMiembro() {
        router.post('/empresa/invitaciones', { email: emailInvitacion }, {
            preserveState: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { invitacion_url?: string } | undefined;
                invitacionUrl = String(flash?.invitacion_url ?? '');
                emailInvitacion = '';
            },
        });
    }
</script>

<AppHead title="Empresa" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <PageHeader
        title={empresa.nombre_comercial ?? empresa.razon_social}
        description="Estado de la solicitud empresarial"
    >
        {#if empresa.puedeOperar}
            <Button href="/gestion/programas/crear">
                <Plus class="mr-2 h-4 w-4" />
                Crear programa
            </Button>
        {/if}
    </PageHeader>

    <Card>
        <CardHeader>
            <CardTitle>Solicitud {empresa.estado}</CardTitle>
            <CardDescription>
                {#if empresa.puedeOperar}
                    La empresa está aprobada y puede operar sus programas.
                {:else}
                    El equipo administrador revisará los datos antes de habilitar la operación.
                {/if}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
            <p><span class="text-muted-foreground">Razón social:</span> {empresa.razon_social}</p>
            <p><span class="text-muted-foreground">Identificador fiscal:</span> {empresa.identificador_fiscal}</p>
            <p><span class="text-muted-foreground">Correo:</span> {empresa.email}</p>
            <p><span class="text-muted-foreground">Rol:</span> {empresa.rol_interno}</p>
            {#if empresa.motivo_estado}
                <p class="border-t border-border pt-3"><span class="text-muted-foreground">Motivo:</span> {empresa.motivo_estado}</p>
            {/if}
        </CardContent>
    </Card>

    {#if empresa.puedeOperar && empresa.rol_interno === 'propietario'}
        <Card>
            <CardHeader><CardTitle>Miembros</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <form class="flex gap-2" onsubmit={(event) => { event.preventDefault(); agregarMiembro(); }}>
                    <Input type="email" bind:value={emailMiembro} placeholder="correo@empresa.com" required />
                    <Button type="submit">Agregar</Button>
                </form>
                <form class="flex gap-2" onsubmit={(event) => { event.preventDefault(); invitarMiembro(); }}>
                    <Input type="email" bind:value={emailInvitacion} placeholder="Invitar por correo" required />
                    <Button type="submit" variant="outline">Invitar</Button>
                </form>
                {#if invitacionUrl}
                    <p class="break-all rounded-md bg-muted p-2 text-xs">Enlace de invitación: <a href={invitacionUrl} class="text-primary underline">{invitacionUrl}</a></p>
                {/if}
                <div class="space-y-2">
                    {#each empresa.usuarios as usuario (usuario.id)}
                        <div class="flex items-center justify-between gap-3 border-b border-border py-2 last:border-0">
                            <div><p class="text-sm font-medium">{usuario.name}</p><p class="text-xs text-muted-foreground">{usuario.email}</p></div>
                            {#if usuario.email !== empresa.email}<Button size="sm" variant="destructive" onclick={() => eliminarMiembro(usuario.id)}>Retirar</Button>{/if}
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}

    {#if empresa.puedeOperar}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2"><CardDescription>Programas</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold">{empresa.resumen.programas}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>Informes recibidos</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold">{empresa.resumen.reportes}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>Pendientes de moderación</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-chart-4">{empresa.resumen.pendientes}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>Aprobados por moderadores</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-chart-1">{empresa.resumen.aprobados}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>Rechazados</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-muted-foreground">{empresa.resumen.rechazados}</p></CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Panel de programas</CardTitle>
                <CardDescription>
                    Publica un programa para que los investigadores puedan verlo y enviar informes.
                    Un programa en borrador no es visible para ellos.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                {#if empresa.programas.length === 0}
                    <p class="text-sm text-muted-foreground">Todavía no hay programas. Crea el primero con "Crear programa".</p>
                {:else}
                    {#each empresa.programas as programa (programa.id)}
                        <div class="flex flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link href={`/programas/${programa.id}`} class="font-medium hover:underline">{programa.nombre}</Link>
                                    <ProgramaStateBadge estado={programa.estado} />
                                    {#if !programa.es_publico}
                                        <span class="text-xs text-muted-foreground">Privado: los investigadores no lo ven</span>
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {programa.reportes_total} informes ·
                                    <span class="text-chart-1">{programa.reportes_aprobados} aprobados</span> ·
                                    {programa.reportes_pendientes} pendientes ·
                                    {programa.reportes_rechazados} rechazados
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                {#if programa.estado === 'borrador'}
                                    <Button size="sm" onclick={() => cambiarEstadoPrograma(programa.id, 'activo')}>Publicar</Button>
                                {:else if programa.estado === 'activo'}
                                    <Button size="sm" variant="outline" onclick={() => cambiarEstadoPrograma(programa.id, 'en_pausa')}>Pausar</Button>
                                {:else if programa.estado === 'en_pausa'}
                                    <Button size="sm" onclick={() => cambiarEstadoPrograma(programa.id, 'activo')}>Reactivar</Button>
                                {/if}
                                <Button size="sm" variant="outline" href={`/gestion/programas/${programa.id}/editar`}>
                                    <Settings class="mr-1 h-3 w-3" />
                                    Editar
                                </Button>
                            </div>
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Informes de vulnerabilidades</CardTitle>
                <CardDescription>
                    Lo que los investigadores encontraron en tus programas. Los borradores no aparecen y
                    solo tu empresa, los moderadores y el autor pueden leerlos.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    {#each filtros as filtro (filtro.valor)}
                        <Button
                            size="sm"
                            variant={filtroEstado === filtro.valor ? 'default' : 'outline'}
                            onclick={() => (filtroEstado = filtro.valor)}
                        >
                            {filtro.etiqueta}
                        </Button>
                    {/each}
                    {#if empresa.programas.length > 1}
                        <select
                            bind:value={filtroPrograma}
                            aria-label="Filtrar por programa"
                            class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                        >
                            <option value="">Todos los programas</option>
                            {#each empresa.programas as programa (programa.id)}
                                <option value={String(programa.id)}>{programa.nombre}</option>
                            {/each}
                        </select>
                    {/if}
                </div>

                {#if reportesFiltrados.length === 0}
                    <p class="text-sm text-muted-foreground">
                        {empresa.reportes.length === 0 ? 'Todavía no hay informes recibidos.' : 'No hay informes con este filtro.'}
                    </p>
                {:else}
                    {#each reportesFiltrados as reporte (reporte.id)}
                        <div class="rounded-md border">
                            <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="space-y-1">
                                    <p class="font-medium">{reporte.numero_reporte} · {reporte.titulo}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {reporte.programa_nombre} · Investigador: {reporte.investigador?.name ?? 'N/D'} · {formatearFecha(reporte.created_at)}
                                    </p>
                                    {#if reporte.aprobado}
                                        <p class="text-xs text-chart-1">
                                            Aprobado por moderador{reporte.aprobado_por ? `: ${reporte.aprobado_por}` : ''}
                                            {reporte.aprobado_en ? `· ${formatearFecha(reporte.aprobado_en)}` : ''}
                                        </p>
                                    {/if}
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <StateBadge estado={reporte.estado} />
                                    {#if reporte.severidad}<SeverityBadge severidad={reporte.severidad} />{/if}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-expanded={informeAbierto === reporte.id}
                                        onclick={() => (informeAbierto = informeAbierto === reporte.id ? null : reporte.id)}
                                    >
                                        {informeAbierto === reporte.id ? 'Ocultar informe' : 'Leer informe'}
                                    </Button>
                                </div>
                            </div>

                            {#if informeAbierto === reporte.id}
                                <div class="space-y-4 border-t p-3">
                                    {#if reporte.cifrado_indisponible}
                                        <p class="text-sm text-destructive">
                                            El contenido cifrado no está disponible: la plataforma no tiene una clave PGP activa.
                                        </p>
                                    {:else}
                                        <div class="grid gap-3 text-sm sm:grid-cols-3">
                                            <p><span class="text-muted-foreground">Categoría:</span> {reporte.categoria ?? 'Sin categoría'}</p>
                                            <p><span class="text-muted-foreground">CVSS:</span> {reporte.puntuacion_cvss ?? 'N/A'}</p>
                                            <p class="truncate"><span class="text-muted-foreground">Vector:</span> {reporte.vector_cvss ?? 'N/A'}</p>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-medium text-muted-foreground">Descripción del bug</p>
                                            <p class="whitespace-pre-wrap text-sm">{reporte.descripcion}</p>
                                        </div>
                                        {#if reporte.poc && Object.keys(reporte.poc).length > 0}
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-muted-foreground">Prueba de concepto (PoC)</p>
                                                <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{JSON.stringify(reporte.poc, null, 2)}</pre>
                                            </div>
                                        {/if}
                                    {/if}
                                    <Button size="sm" variant="outline" href={`/reportes/${reporte.id}`}>Abrir informe completo y línea de tiempo</Button>
                                </div>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>