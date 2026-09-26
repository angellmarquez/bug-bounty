<script module lang="ts">
    import { index as reportesIndex, edit as reportesEdit, show as reportesShow } from '@/routes/reportes';
    import { show as programasShow } from '@/routes/programas';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reportes',
                href: reportesIndex(),
            },
            {
                title: 'Detalles',
            }
        ],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import CheckCircle from '@lucide/svelte/icons/check-circle';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import Copy from '@lucide/svelte/icons/copy';
    import Wrench from '@lucide/svelte/icons/wrench';
    import Lock from '@lucide/svelte/icons/lock';
    import Edit from '@lucide/svelte/icons/edit';
    import Send from '@lucide/svelte/icons/send';
    import Eye from '@lucide/svelte/icons/eye';
    import HelpCircle from '@lucide/svelte/icons/help-circle';
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Timeline from '@/components/Timeline.svelte';
    import ComentarioForm from '@/components/ComentarioForm.svelte';
    import StateTransition from '@/components/StateTransition.svelte';
    import { Button } from '@/components/ui/button';
    import ContenidoInforme from '@/components/ContenidoInforme.svelte';
    import GaleriaFotos from '@/components/GaleriaFotos.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import EstadoProgreso from '@/components/EstadoProgreso.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { index as reportesRoute } from '@/routes/reportes';
    import type { FotoAdjunta, Programa, Reporte } from '@/types/domain';
    import type { EstadoReporte } from '@/types/enums';

    let {
        reporte: initialReporte,
        puedeVerNotasInternas,
        puedeTriar = false,
        puedeModerar = false,
        cifradoIndisponible = false,
        fotos = [],
        historialInvestigador = null,
        accionesDisponibles = {},
        moderadoresAsignables = [],
        candidatosDuplicado = [],
        esperaTurno = null,
        avisoCola = null,
    }: {
        reporte: Reporte;
        puedeVerNotasInternas: boolean;
        puedeTriar?: boolean;
        puedeModerar?: boolean;
        cifradoIndisponible?: boolean;
        fotos?: FotoAdjunta[];
        historialInvestigador?: {
            reputation_score: number;
            informes: number;
            aprobados: number;
            descartados: number;
        } | null;
        accionesDisponibles?: Record<string, boolean>;
        moderadoresAsignables?: { id: number; name: string }[];
        candidatosDuplicado?: { id: number; numero_reporte: string; titulo: string; estado: string }[];
        esperaTurno?: string | null;
        avisoCola?: string | null;
    } = $props();

    // Derivado: tras cada acción de triaje Inertia entrega props nuevas a esta misma instancia.
    const reporte = $derived(initialReporte);
    const auth = $derived(page.props.auth);

    let transitionOpen = $state(false);
    let transitionAccion = $state<'asignar' | 'pedir_info' | 'validar' | 'rechazar' | 'marcar_duplicado' | 'reparacion' | 'cerrar'>('validar');

    function openTransition(accion: typeof transitionAccion) {
        transitionAccion = accion;
        transitionOpen = true;
    }

    // Datos del programa al que se envió el informe (la empresa y su estado vienen del servidor).
    const programaInforme = $derived(
        reporte.programa as (Programa & { empresa_nombre?: string | null }) | undefined,
    );

    // "Volver" lleva a la lista desde la que este rol suele llegar al informe.
    const destinoVolver = $derived.by(() => {
        const roles = (page.props.userRoles as string[] | undefined) ?? [];
        if (puedeModerar && reporte.programa) {
            return { href: `/moderacion/programas/${reporte.programa.id}`, etiqueta: 'Volver a la cola del programa' };
        }
        if (roles.includes('empresa')) return { href: '/empresa/reportes', etiqueta: 'Volver a informes recibidos' };
        return { href: reportesRoute(), etiqueta: 'Volver a mis reportes' };
    });

    function iniciarRevision() {
        router.post(`/reportes/${reporte.id}/revisar`, {}, { preserveScroll: true });
    }

    function enviarReporte() {
        if (!confirm('¿Estás seguro de enviar este reporte? Ya no podrás editarlo.')) return;
        router.post(`/reportes/${reporte.id}/enviar`);
    }

    function formatearFecha(dateStr: string | null): string {
        if (!dateStr) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }

    function recargar() {
        router.reload({ only: ['reporte'] });
    }
</script>

<AppHead title="{reporte.numero_reporte} — {reporte.titulo}" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between w-full">
        <div class="flex items-center gap-4">
            <BotonVolver href={destinoVolver.href} etiqueta={destinoVolver.etiqueta} />
            <PageHeader
                title={reporte.numero_reporte}
                description={reporte.titulo}
            />
        </div>
        {#if (reporte.estado === 'borrador' || reporte.estado === 'needs_info') && auth?.user?.id === reporte.investigador_id}
            <div class="flex items-center gap-2">
                <Button variant="outline" href={reportesEdit(reporte.id)}>
                    <Edit class="mr-2 h-4 w-4" />
                    Editar
                </Button>
                <Button onclick={enviarReporte}>
                    <Send class="mr-2 h-4 w-4" />
                    {reporte.estado === 'needs_info' ? 'Reenviar información' : 'Enviar'}
                </Button>
            </div>
        {/if}
    </div>

    {#if reporte.estado === 'needs_info'}
        <div role="status" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-600 dark:text-amber-400">
            <p class="font-semibold">Información adicional solicitada</p>
            <p class="mt-1 text-xs">
                El equipo de moderación ha solicitado detalles o evidencias adicionales para continuar el triaje. Por favor edita el reporte y pulsa «Reenviar información».
            </p>
        </div>
    {/if}

    {#if programaInforme}
        <Card>
            <CardContent class="space-y-5 pt-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Programa</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-lg font-semibold">{programaInforme.nombre}</p>
                            <ProgramaStateBadge estado={programaInforme.estado} />
                        </div>
                        {#if programaInforme.empresa_nombre}
                            <p class="text-sm text-muted-foreground">Empresa: {programaInforme.empresa_nombre}</p>
                        {/if}
                    </div>
                    <Button variant="outline" href={programasShow(programaInforme.id)}>
                        <ExternalLink class="mr-2 h-4 w-4" />
                        Ver programa
                    </Button>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Estado del informe</p>
                    <EstadoProgreso estado={reporte.estado} />
                </div>
            </CardContent>
        </Card>
    {/if}

    {#if avisoCola}
        <div role="status" class="rounded-md border border-chart-2/50 bg-chart-2/10 p-3 text-sm text-chart-2" data-test="aviso-cola">
            {avisoCola}
        </div>
    {/if}

    {#if esperaTurno}
        <div role="status" class="rounded-md border border-chart-4/50 bg-chart-4/10 p-3 text-sm text-chart-4" data-test="espera-turno">
            {esperaTurno}
        </div>
    {/if}

    {#if page.props.errors?.estado}
        <div role="alert" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            {page.props.errors.estado}
        </div>
    {/if}

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Informe del investigador</CardTitle>
                    <CardDescription>Todo lo que envió el investigador, tal cual.</CardDescription>
                </CardHeader>
                <CardContent>
                    <ContenidoInforme
                        descripcion={reporte.descripcion}
                        poc={reporte.poc}
                        pocSchema={reporte.programa?.poc_schema}
                        categoria={reporte.categoria}
                        vectorCvss={reporte.vector_cvss}
                        puntuacionCvss={reporte.puntuacion_cvss}
                        {cifradoIndisponible}
                    />
                </CardContent>
            </Card>

            {#if fotos.length > 0}
                <div data-test="fotos-evidencia"><Card>
                    <CardHeader>
                        <CardTitle>Fotos de evidencia</CardTitle>
                        <CardDescription>Se guardan cifradas; la huella SHA-256 prueba que no cambiaron desde que se subieron.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <GaleriaFotos {fotos} />
                    </CardContent>
                </Card></div>
            {/if}

            {#if puedeVerNotasInternas && reporte.notas_internas}
                <Card>
                    <CardHeader>
                        <CardTitle>Notas internas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="whitespace-pre-wrap text-sm text-muted-foreground">{reporte.notas_internas}</p>
                    </CardContent>
                </Card>
            {/if}

            {#if puedeTriar}
                <Card>
                    <CardHeader>
                        <CardTitle>Acciones de triaje</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            {#if accionesDisponibles.revisar}
                                <Button size="sm" onclick={iniciarRevision}>
                                    <Eye class="mr-1 h-3 w-3" />
                                    Iniciar revisión
                                </Button>
                            {/if}
                            {#if accionesDisponibles.asignar}
                                <Button variant="outline" size="sm" onclick={() => openTransition('asignar')}>
                                    <UserPlus class="mr-1 h-3 w-3" />
                                    Asignar
                                </Button>
                            {/if}
                            {#if accionesDisponibles.pedir_info}
                                <Button variant="outline" size="sm" onclick={() => openTransition('pedir_info')}>
                                    <HelpCircle class="mr-1 h-3 w-3" />
                                    Pedir información
                                </Button>
                            {/if}
                            {#if accionesDisponibles.validar}
                                <Button variant="outline" size="sm" onclick={() => openTransition('validar')}>
                                    <CheckCircle class="mr-1 h-3 w-3" />
                                    Validar
                                </Button>
                            {/if}
                            {#if accionesDisponibles.rechazar}
                                <Button variant="outline" size="sm" onclick={() => openTransition('rechazar')}>
                                    <XCircle class="mr-1 h-3 w-3" />
                                    Rechazar
                                </Button>
                            {/if}
                            {#if accionesDisponibles.marcar_duplicado}
                                <Button variant="outline" size="sm" onclick={() => openTransition('marcar_duplicado')}>
                                    <Copy class="mr-1 h-3 w-3" />
                                    Duplicado
                                </Button>
                            {/if}
                            {#if accionesDisponibles.reparacion}
                                <Button variant="outline" size="sm" onclick={() => openTransition('reparacion')}>
                                    <Wrench class="mr-1 h-3 w-3" />
                                    En reparación
                                </Button>
                            {/if}
                            {#if accionesDisponibles.cerrar}
                                <Button variant="outline" size="sm" onclick={() => openTransition('cerrar')}>
                                    <Lock class="mr-1 h-3 w-3" />
                                    Cerrar como resuelto
                                </Button>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/if}
        </div>

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Detalles</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Estado</span>
                        <StateBadge estado={reporte.estado} />
                    </div>

                    {#if reporte.severidad}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Severidad</span>
                            <SeverityBadge severidad={reporte.severidad} />
                        </div>
                    {/if}

                    {#if reporte.programa}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Programa</span>
                            <Link
                                href={programasShow(reporte.programa.id)}
                                class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                            >
                                {reporte.programa.nombre}
                                <ExternalLink class="h-3 w-3" />
                            </Link>
                        </div>
                    {/if}

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Investigador</span>
                        <span class="text-sm">{reporte.investigador?.name ?? 'Desconocido'}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Asignado a</span>
                        <span class="text-sm">{reporte.asignadoA?.name ?? 'Sin asignar'}</span>
                    </div>

                    {#if reporte.vector_cvss}
                        <div class="space-y-1">
                            <span class="text-sm text-muted-foreground">CVSS</span>
                            <div class="flex items-center gap-2">
                                {#if reporte.puntuacion_cvss}
                                    <span class="text-lg font-bold text-primary">{reporte.puntuacion_cvss}</span>
                                {/if}
                                <code class="flex-1 truncate rounded bg-muted px-2 py-1 text-xs">{reporte.vector_cvss}</code>
                            </div>
                        </div>
                    {/if}

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Creado</span>
                        <span class="text-sm">{formatearFecha(reporte.created_at)}</span>
                    </div>

                    {#if reporte.enviado_en}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Enviado</span>
                            <span class="text-sm">{formatearFecha(reporte.enviado_en)}</span>
                        </div>
                    {/if}

                    {#if reporte.cerrado_en}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Cerrado</span>
                            <span class="text-sm">{formatearFecha(reporte.cerrado_en)}</span>
                        </div>
                    {/if}
                </CardContent>
            </Card>

            {#if puedeModerar && historialInvestigador}
                <Card>
                    <CardHeader>
                        <CardTitle>Investigador</CardTitle>
                        <CardDescription>Su historial ayuda a valorar el informe.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Nombre</span>
                            <span class="text-sm">{reporte.investigador?.name ?? 'Desconocido'}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Reputación</span>
                            <span class="flex items-center gap-2 text-sm font-semibold text-primary">
                                {historialInvestigador.reputation_score}
                                <RangoBadge puntos={historialInvestigador.reputation_score} />
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Informes enviados</span>
                            <span class="text-sm">{historialInvestigador.informes}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Aprobados</span>
                            <span class="text-sm text-chart-1">{historialInvestigador.aprobados}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Descartados</span>
                            <span class="text-sm {historialInvestigador.descartados > 0 ? 'text-chart-4' : ''}">{historialInvestigador.descartados}</span>
                        </div>
                    </CardContent>
                </Card>
            {/if}

            {#if puedeModerar && reporte.programa}
                <Card>
                    <CardHeader>
                        <CardTitle>Alcance del programa</CardTitle>
                        <CardDescription>Comprueba que el hallazgo esté dentro de lo que la empresa quiere revisar.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        {#if reporte.programa.bugs_buscados}
                            <div class="space-y-1">
                                <p class="text-xs font-medium text-muted-foreground">Bugs que buscan</p>
                                <p class="whitespace-pre-wrap text-sm">{reporte.programa.bugs_buscados}</p>
                            </div>
                        {/if}
                        <div class="space-y-1">
                            <p class="text-xs font-medium text-muted-foreground">Objetivos</p>
                            {#if reporte.programa.objetivos && reporte.programa.objetivos.length > 0}
                                {#each reporte.programa.objetivos as objetivo (objetivo.id)}
                                    <p class="text-sm">
                                        <span class="text-xs uppercase text-muted-foreground">{objetivo.tipo}</span>
                                        {objetivo.valor}
                                        {#if objetivo.descripcion}<span class="text-xs text-muted-foreground"> · {objetivo.descripcion}</span>{/if}
                                    </p>
                                {/each}
                            {:else}
                                <p class="text-sm text-muted-foreground">El programa no define objetivos.</p>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/if}

            {#if reporte.duplicadoDe}
                <Card>
                    <CardHeader>
                        <CardTitle>Duplicado de</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Link
                            href={reportesShow(reporte.duplicadoDe.id)}
                            class="text-sm text-primary hover:underline"
                        >
                            {reporte.duplicadoDe.numero_reporte} — {reporte.duplicadoDe.titulo}
                        </Link>
                    </CardContent>
                </Card>
            {/if}
        </div>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>Linea de tiempo</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            {#if puedeTriar}
                <ComentarioForm reporteId={reporte.id} onsuccess={recargar} />
            {/if}
            <Timeline eventos={reporte.eventos ?? []} />
        </CardContent>
    </Card>
</div>

<StateTransition
    bind:open={transitionOpen}
    accion={transitionAccion}
    reporteId={reporte.id}
    estadoActual={reporte.estado}
    {moderadoresAsignables}
    {candidatosDuplicado}
    onsuccess={recargar}
/>
