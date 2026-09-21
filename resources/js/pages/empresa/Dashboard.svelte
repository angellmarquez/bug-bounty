<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Empresa', href: '/empresa' }],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Link } from '@inertiajs/svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Settings from '@lucide/svelte/icons/settings';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { toast } from 'svelte-sonner';
    import ReportesCompactos from '@/components/ReportesCompactos.svelte';
    import type { EstadoPrograma } from '@/types/enums';
    import type { ReporteCompacto } from '@/types/domain';

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
            esAdmin: boolean;
            puedeOperar: boolean;
            puedeGestionarMiembros: boolean;
            usuarios: { id: number; name: string; email: string }[];
            invitaciones: { id: number; email: string; expira_en: string; url: string }[];
            programas: {
                id: number;
                nombre: string;
                estado: EstadoPrograma;
                es_publico: boolean;
                objetivos_count: number;
                reportes_todos: number;
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
            reportes: ReporteCompacto[];
        };
    } = $props();

    function cambiarEstadoPrograma(programaId: number, estado: 'activo' | 'en_pausa' | 'archivado') {
        if (estado === 'activo' && !confirm('¿Publicar este programa? Los investigadores podrán verlo y enviar reportes.')) return;
        if (estado === 'archivado' && !confirm('¿Archivar este programa? Dejará de aceptar nuevos reportes.')) return;
        router.post(`/programas/${programaId}/cambiar-estado`, { estado }, { preserveScroll: true });
    }

    function eliminarPrograma(programaId: number, nombre: string) {
        if (!confirm(`¿Eliminar el programa "${nombre}"? Esta acción no se puede deshacer.`)) return;
        router.delete(`/programas/${programaId}`, { preserveScroll: true });
    }

    // Errores del servidor al publicar o eliminar un programa.
    const errorPrograma = $derived(page.props.errors?.estado ?? page.props.errors?.programa);

    let emailMiembro = $state('');
    let emailInvitacion = $state('');

    async function copiarEnlace(url: string) {
        try {
            await navigator.clipboard.writeText(url);
            toast.success('Enlace copiado.');
        } catch {
            toast.error('No se pudo copiar; selecciónalo y cópialo a mano.');
        }
    }

    // Un administrador opera cualquier empresa: hay que decir sobre cuál.
    const contexto = $derived(empresa.esAdmin ? { empresa_id: empresa.id } : {});
    const sufijoAdmin = $derived(empresa.esAdmin ? `?empresa=${empresa.id}` : '');

    function agregarMiembro() {
        router.post('/empresa/miembros', { email: emailMiembro, ...contexto }, {
            preserveState: true,
            onSuccess: () => { emailMiembro = ''; },
        });
    }

    function eliminarMiembro(userId: number) {
        router.delete(`/empresa/miembros/${userId}${empresa.esAdmin ? `?empresa_id=${empresa.id}` : ''}`, { preserveState: true });
    }

    function invitarMiembro() {
        router.post('/empresa/invitaciones', { email: emailInvitacion, ...contexto }, {
            preserveState: true,
            onSuccess: () => {
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
            <Button href={`/gestion/programas/crear${sufijoAdmin}`}>
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

    {#if empresa.puedeGestionarMiembros}
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
                {#if empresa.invitaciones.length > 0}
                    <div class="space-y-2">
                        <p class="text-sm font-medium">Invitaciones pendientes</p>
                        <p class="text-xs text-muted-foreground">
                            La persona debe iniciar sesión (o registrarse) con ese mismo correo y abrir el enlace para unirse.
                        </p>
                        {#each empresa.invitaciones as invitacion (invitacion.id)}
                            <div class="flex flex-col gap-2 rounded-md bg-muted p-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-medium">{invitacion.email}</p>
                                    <p class="break-all text-muted-foreground">{invitacion.url}</p>
                                </div>
                                <Button size="sm" variant="outline" type="button" onclick={() => copiarEnlace(invitacion.url)}>Copiar enlace</Button>
                            </div>
                        {/each}
                    </div>
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

        {#if errorPrograma}
            <div role="alert" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                {errorPrograma}
            </div>
        {/if}

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
                                    {#if programa.estado === 'borrador' && programa.objetivos_count === 0}
                                        <span class="text-xs text-chart-4">Falta al menos un objetivo para publicarlo</span>
                                    {/if}
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
                                    <Button
                                        size="sm"
                                        disabled={programa.objetivos_count === 0}
                                        title={programa.objetivos_count === 0 ? 'Agrega al menos un objetivo para poder publicarlo' : undefined}
                                        onclick={() => cambiarEstadoPrograma(programa.id, 'activo')}
                                    >Publicar</Button>
                                {:else if programa.estado === 'activo'}
                                    <Button size="sm" variant="outline" onclick={() => cambiarEstadoPrograma(programa.id, 'en_pausa')}>Pausar</Button>
                                {:else if programa.estado === 'en_pausa'}
                                    <Button size="sm" onclick={() => cambiarEstadoPrograma(programa.id, 'activo')}>Reactivar</Button>
                                {/if}
                                {#if programa.estado === 'activo' || programa.estado === 'en_pausa'}
                                    <Button size="sm" variant="outline" onclick={() => cambiarEstadoPrograma(programa.id, 'archivado')}>Archivar</Button>
                                {/if}
                                <Button size="sm" variant="outline" href={`/gestion/programas/${programa.id}/editar`}>
                                    <Settings class="mr-1 h-3 w-3" />
                                    Editar
                                </Button>
                                {#if programa.reportes_todos === 0}
                                    <Button size="sm" variant="destructive" onclick={() => eliminarPrograma(programa.id, programa.nombre)}>
                                        Eliminar
                                    </Button>
                                {/if}
                            </div>
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="space-y-1.5">
                        <CardTitle>Últimos informes recibidos</CardTitle>
                        <CardDescription>
                            Vista rápida con el investigador y su reputación. Abre un informe para leer la
                            descripción y la prueba de concepto. Los borradores no aparecen.
                        </CardDescription>
                    </div>
                    <Button href={`/empresa/reportes${sufijoAdmin}`} variant="outline">
                        Ver todos los informes ({empresa.resumen.reportes})
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                {#if empresa.reportes.length === 0}
                    <p class="text-sm text-muted-foreground">Todavía no hay informes recibidos.</p>
                {:else}
                    <ReportesCompactos reportes={empresa.reportes} />
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>