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
    import { Link } from '@inertiajs/svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Settings from '@lucide/svelte/icons/settings';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import Archive from '@lucide/svelte/icons/archive';
    import Ellipsis from '@lucide/svelte/icons/ellipsis';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
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
            programas: {
                id: number;
                nombre: string;
                estado: EstadoPrograma;
                es_publico: boolean;
                objetivos_count: number;
                reportes_todos: number;
                reportes_total: number;
                reportes_validados: number;
                reportes_en_reparacion: number;
                reportes_cerrados: number;
            }[];
            resumen: {
                programas: number;
                reportes: number;
                validados: number;
                en_reparacion: number;
                cerrados: number;
            };
            reportes: ReporteCompacto[];
        };
    } = $props();

    function cambiarEstadoPrograma(programaId: number, estado: 'activo' | 'en_pausa' | 'archivado') {
        if (estado === 'activo' && !confirm('¿Publicar este programa? Los investigadores podrán verlo y enviar reportes.')) return;
        if (estado === 'archivado' && !confirm('¿Archivar este programa? Dejará de aceptar nuevos reportes.')) return;
        router.post(`/programas/${programaId}/cambiar-estado`, { estado }, { preserveScroll: true });
    }

    function resolverPrograma(programaId: number, nombre: string) {
        if (!confirm(`¿Poner el programa "${nombre}" como resuelto? Dejará de recibir nuevos informes de vulnerabilidad.`)) return;
        router.post(`/programas/${programaId}/resolver`, {}, { preserveScroll: true });
    }

    function eliminarPrograma(programaId: number, nombre: string) {
        if (!confirm(`¿Eliminar el programa "${nombre}"? Esta acción no se puede deshacer.`)) return;
        router.delete(`/programas/${programaId}`, { preserveScroll: true });
    }

    // Errores del servidor al publicar o eliminar un programa.
    const errorPrograma = $derived(page.props.errors?.estado ?? page.props.errors?.programa);

    // Un administrador opera cualquier empresa: hay que decir sobre cuál.
    const sufijoAdmin = $derived(empresa.esAdmin ? `?empresa=${empresa.id}` : '');
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
            {#if empresa.motivo_estado}
                <p class="border-t border-border pt-3"><span class="text-muted-foreground">Motivo:</span> {empresa.motivo_estado}</p>
            {/if}
        </CardContent>
    </Card>

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
                <CardHeader class="pb-2"><CardDescription>Validados</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-chart-1">{empresa.resumen.validados}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>En reparación</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-chart-4">{empresa.resumen.en_reparacion}</p></CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardDescription>Cerrados</CardDescription></CardHeader>
                <CardContent><p class="text-2xl font-bold text-muted-foreground">{empresa.resumen.cerrados}</p></CardContent>
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
                                    {programa.reportes_total} informes recibidos
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
                                <Button size="sm" variant="outline" href={`/gestion/programas/${programa.id}/editar`}>
                                    <Settings class="mr-1 h-3 w-3" />
                                    Editar
                                </Button>
                                <!-- Acciones menos frecuentes (y la destructiva) en un menú, para no competir con las principales. -->
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        {#snippet children(props)}
                                            <Button size="sm" variant="ghost" aria-label="Más acciones de {programa.nombre}" {...props}>
                                                <Ellipsis class="h-4 w-4" />
                                            </Button>
                                        {/snippet}
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-52">
                                        {#if programa.estado === 'activo' || programa.estado === 'en_pausa'}
                                            <DropdownMenuItem asChild>
                                                {#snippet children(props)}
                                                    <button type="button" class={props.class} onclick={() => { props.onClick?.(); resolverPrograma(programa.id, programa.nombre); }}>
                                                        <CheckCircle class="mr-2 h-4 w-4 text-exito" /> Poner como resuelto
                                                    </button>
                                                {/snippet}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                {#snippet children(props)}
                                                    <button type="button" class={props.class} onclick={() => { props.onClick?.(); cambiarEstadoPrograma(programa.id, 'archivado'); }}>
                                                        <Archive class="mr-2 h-4 w-4" /> Archivar
                                                    </button>
                                                {/snippet}
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                        {/if}
                                        <DropdownMenuItem asChild>
                                            {#snippet children(props)}
                                                <button type="button" class="{props.class} text-destructive hover:text-destructive" onclick={() => { props.onClick?.(); eliminarPrograma(programa.id, programa.nombre); }}>
                                                    <Trash2 class="mr-2 h-4 w-4" /> Eliminar programa
                                                </button>
                                            {/snippet}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
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