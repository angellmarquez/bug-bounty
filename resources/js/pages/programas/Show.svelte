<script module lang="ts">
    import { index as programasIndex, edit as programaEdit } from '@/routes/programas';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Programas',
                href: programasIndex(),
            },
            {
                title: 'Detalles',
            }
        ],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Bug from '@lucide/svelte/icons/bug';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Settings from '@lucide/svelte/icons/settings';
    import Edit from '@lucide/svelte/icons/edit';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
    import NivelAccesoBadge from '@/components/NivelAccesoBadge.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Separator } from '@/components/ui/separator';
    import { gestion as gestionRoute } from '@/routes/programas';
    import InformesDelPrograma from '@/components/InformesDelPrograma.svelte';
    import type { Programa, ObjetivoPrograma, ReporteCompacto } from '@/types/domain';

    // Una suspensión vigente oculta el botón de reportar: se explica el motivo.
    const suspension = $derived((page.props.cuenta as { suspension: unknown } | null | undefined)?.suspension ?? null);

    let {
        programa,
        puedeReportar,
        puedeGestionar,
        puedeEditar = false,
        puedeCambiarEstado = false,
        puedeEliminar = false,
        transicionesPermitidas = [],
        puedeModerar = false,
        moderaEstePrograma = false,
        esDeMiEmpresa = false,
        filtroInformes = 'por_revisar',
        conteosInformes = null,
        informes = [],
    }: {
        programa: Programa & {
            empresa?: { nombre: string; sitio_web: string | null } | null;
            creador?: { id: number; name: string } | null;
            objetivos?: ObjetivoPrograma[];
            reportes_count?: number;
        };
        puedeReportar: boolean;
        puedeGestionar: boolean;
        puedeEditar?: boolean;
        puedeCambiarEstado?: boolean;
        puedeEliminar?: boolean;
        transicionesPermitidas?: string[];
        puedeModerar?: boolean;
        moderaEstePrograma?: boolean;
        esDeMiEmpresa?: boolean;
        filtroInformes?: 'por_revisar' | 'en_revision' | 'aprobados' | 'rechazados' | 'todos';
        conteosInformes?: Record<'por_revisar' | 'en_revision' | 'aprobados' | 'rechazados' | 'todos', number> | null;
        informes?: ReporteCompacto[];
    } = $props();

    function cambiarEstado(estado: string) {
        if (estado === 'archivado' && !confirm('¿Archivar este programa? Dejará de aceptar nuevos reportes.')) return;
        router.post(`/programas/${programa.id}/cambiar-estado`, { estado });
    }

    function eliminarPrograma() {
        if (!confirm(`¿Eliminar el programa "${programa.nombre}"? Esta acción no se puede deshacer.`)) return;
        router.delete(`/programas/${programa.id}`);
    }

    const sinObjetivos = $derived((programa.objetivos?.length ?? 0) === 0);
    const errorPrograma = $derived(page.props.errors?.estado ?? page.props.errors?.programa);

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

    function tipoPocLabel(type: string): string {
        const labels: Record<string, string> = {
            text: 'Texto',
            textarea: 'Texto largo',
            select: 'Seleccion',
            number: 'Numero',
            url: 'URL',
            code: 'Codigo',
        };
        return labels[type] ?? type;
    }

    // Cada rol llega al programa desde un sitio distinto: "volver" respeta ese origen.
    const destinoVolver = $derived(
        puedeModerar
            ? { href: '/moderacion', etiqueta: 'Volver a moderación' }
            : puedeEditar
              ? { href: '/empresa', etiqueta: 'Volver al panel de empresa' }
              : { href: programasIndex(), etiqueta: 'Volver a programas' },
    );

    const urlReportar = $derived(`/reportes/crear?programa=${programa.id}`);
    const enPausa = $derived(programa.estado === 'en_pausa');
</script>

<AppHead title={programa.nombre} />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    {#if errorPrograma}
        <div role="alert" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            {errorPrograma}
        </div>
    {/if}

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <BotonVolver href={destinoVolver.href} etiqueta={destinoVolver.etiqueta} />
            <PageHeader
                title={programa.nombre}
                description={programa.empresa ? `Programa de ${programa.empresa.nombre}` : undefined}
            />
            <ProgramaStateBadge estado={programa.estado} />
        </div>
        {#if puedeReportar}
            <Button href={urlReportar}>
                <Bug class="mr-2 h-4 w-4" />
                Reportar un bug
            </Button>
        {/if}
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {#if puedeModerar && conteosInformes}
                <InformesDelPrograma
                    programaId={programa.id}
                    filtro={filtroInformes}
                    conteos={conteosInformes}
                    {informes}
                />
            {/if}

            {#if programa.empresa}
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-4 w-4" />
                            Sobre la empresa
                        </CardTitle>
                        <CardDescription>{programa.empresa.nombre}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="whitespace-pre-wrap text-sm">{programa.descripcion}</p>
                        {#if programa.empresa.sitio_web}
                            <p class="text-sm">
                                <span class="text-muted-foreground">Sitio web:</span>
                                <a
                                    href={programa.empresa.sitio_web}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary hover:underline"
                                >{programa.empresa.sitio_web}</a>
                            </p>
                        {/if}
                    </CardContent>
                </Card>
            {:else}
                <Card>
                    <CardHeader><CardTitle>Descripcion</CardTitle></CardHeader>
                    <CardContent>
                        <p class="whitespace-pre-wrap text-sm">{programa.descripcion}</p>
                    </CardContent>
                </Card>
            {/if}

            <Card>
                <CardHeader>
                    <CardTitle>Qué bugs buscamos</CardTitle>
                    <CardDescription>Tipos de vulnerabilidad que este programa quiere que le reportes.</CardDescription>
                </CardHeader>
                <CardContent>
                    {#if programa.bugs_buscados}
                        <p class="whitespace-pre-wrap text-sm">{programa.bugs_buscados}</p>
                    {:else}
                        <p class="text-sm text-muted-foreground">
                            La empresa no detalló tipos concretos. Revisa los objetivos y el alcance de abajo.
                        </p>
                    {/if}
                </CardContent>
            </Card>

            {#if programa.objetivos && programa.objetivos.length > 0}
                <Card>
                    <CardHeader>
                        <CardTitle>Alcance</CardTitle>
                        <CardDescription>Sistemas en los que puedes investigar.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        {#each programa.objetivos as obj (obj.id)}
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary">{tipoObjetivoLabel(obj.tipo)}</Badge>
                                    <span class="text-sm font-medium text-foreground">{obj.valor}</span>
                                </div>
                                {#if obj.descripcion}
                                    <p class="text-xs text-muted-foreground">{obj.descripcion}</p>
                                {/if}
                            </div>
                        {/each}
                    </CardContent>
                </Card>
            {/if}

            {#if programa.poc_schema && programa.poc_schema.length > 0}
                <Card>
                    <CardHeader>
                        <CardTitle>Qué necesitarás para tu reporte</CardTitle>
                        <CardDescription>
                            El formulario de reporte se adapta a este programa y te pedirá estos datos de tu prueba de concepto.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            {#each programa.poc_schema as campo, i (i)}
                                <div class="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-medium">{campo.label}</span>
                                        {#if campo.required}
                                            <Badge variant="destructive" class="text-[10px]">Requerido</Badge>
                                        {/if}
                                    </div>
                                    <span class="text-xs text-muted-foreground">{tipoPocLabel(campo.type)}</span>
                                </div>
                            {/each}
                        </div>
                    </CardContent>
                </Card>
            {/if}
        </div>

        <div class="space-y-6">
            {#if puedeReportar}
                <Card>
                    <CardHeader>
                        <CardTitle>¿Encontraste algo?</CardTitle>
                        <CardDescription>
                            Envía tu hallazgo con el formulario de este programa y sigue su estado desde tu panel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button href={urlReportar} class="w-full">
                            <Bug class="mr-2 h-4 w-4" />
                            Reportar un bug
                        </Button>
                    </CardContent>
                </Card>
            {:else if suspension}
                <Card class="border-rose-500/40">
                    <CardHeader>
                        <CardTitle>Tu cuenta está suspendida</CardTitle>
                        <CardDescription>
                            No puedes enviar informes nuevos hasta que termine la suspensión. Puedes apelarla desde Mi reputación.
                        </CardDescription>
                    </CardHeader>
                </Card>
            {:else if moderaEstePrograma}
                <Card class="border-violet-500/40">
                    <CardHeader>
                        <CardTitle>Moderas este programa</CardTitle>
                        <CardDescription>
                            Como moderador ves los informes de los demás investigadores, así que no puedes enviar los tuyos a este
                            programa: sería un conflicto de interés. En los demás programas puedes reportar con normalidad.
                        </CardDescription>
                    </CardHeader>
                </Card>
            {:else if esDeMiEmpresa}
                <div data-test="aviso-mi-empresa"><Card class="border-sky-500/40">
                    <CardHeader>
                        <CardTitle>Este programa es de tu empresa</CardTitle>
                        <CardDescription>
                            Como miembro no puedes enviarle informes: sería un conflicto de interés. Sigues viendo el estado de los que ya
                            presentaste antes de unirte y puedes reportar a los programas de otras empresas.
                        </CardDescription>
                    </CardHeader>
                </Card></div>
            {:else if enPausa && !puedeGestionar}
                <Card>
                    <CardContent class="pt-6 text-sm text-muted-foreground">
                        Este programa está en pausa: por ahora no acepta nuevos reportes.
                    </CardContent>
                </Card>
            {/if}

            <Card>
                <CardHeader>
                    <CardTitle>Detalles</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Estado</span>
                        <ProgramaStateBadge estado={programa.estado} />
                    </div>

                    <Separator />

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Nivel de acceso</span>
                        <NivelAccesoBadge nivel={programa.nivel_acceso} />
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Reportes recibidos</span>
                        <span class="text-sm">{programa.reportes_count ?? 0}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">PoC requerido</span>
                        <span class="text-sm">{programa.requiere_poc ? 'Si' : 'No'}</span>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Inicio</span>
                            <span class="text-sm">{formatearFecha(programa.inicia_en)}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Fin</span>
                            <span class="text-sm">{formatearFecha(programa.termina_en)}</span>
                        </div>
                    </div>

                    {#if programa.creador}
                        <Separator />
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Creado por</span>
                            <span class="text-sm">{programa.creador.name}</span>
                        </div>
                    {/if}
                </CardContent>
            </Card>

            {#if puedeEditar || puedeGestionar}
                <div class="flex flex-col gap-2 w-full">
                    {#if puedeEditar}
                        <Button variant="outline" href={programaEdit(programa.id)} class="w-full">
                            <Edit class="mr-2 h-4 w-4" />
                            Editar Programa
                        </Button>
                    {/if}
                    {#if puedeGestionar}
                        <Button variant="outline" href={gestionRoute()} class="w-full">
                            <Settings class="mr-2 h-4 w-4" />
                            Gestionar
                        </Button>
                    {/if}
                </div>
            {/if}

            {#if puedeCambiarEstado && transicionesPermitidas.length > 0}
                <div class="flex flex-col gap-2 w-full">
                    {#each transicionesPermitidas as estado}
                        <Button
                            variant={estado === 'activo' ? 'default' : 'outline'}
                            class="w-full"
                            disabled={estado === 'activo' && sinObjetivos}
                            onclick={() => cambiarEstado(estado)}
                        >
                            {estado === 'activo' ? 'Publicar programa' : `Cambiar a ${estado}`}
                        </Button>
                    {/each}
                    {#if sinObjetivos && transicionesPermitidas.includes('activo')}
                        <p class="text-xs text-chart-4">
                            {puedeEditar
                                ? 'Para publicarlo, primero define al menos un objetivo en "Editar Programa".'
                                : 'Para publicarlo, la empresa debe definir al menos un objetivo.'}
                        </p>
                    {/if}
                </div>
            {/if}

            {#if puedeEliminar}
                <Button variant="destructive" class="w-full" onclick={eliminarPrograma}>
                    Eliminar programa
                </Button>
            {/if}
        </div>
    </div>
</div>
