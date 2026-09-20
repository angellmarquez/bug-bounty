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
    import { router } from '@inertiajs/svelte';
    import Bug from '@lucide/svelte/icons/bug';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Settings from '@lucide/svelte/icons/settings';
    import Edit from '@lucide/svelte/icons/edit';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import ProgramaStateBadge from '@/components/ProgramaStateBadge.svelte';
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
    import type { Programa, ObjetivoPrograma } from '@/types/domain';

    let {
        programa,
        puedeReportar,
        puedeGestionar,
        puedeCambiarEstado = false,
        transicionesPermitidas = [],
    }: {
        programa: Programa & {
            empresa?: { nombre: string; sitio_web: string | null } | null;
            creador?: { id: number; name: string } | null;
            objetivos?: ObjetivoPrograma[];
            reportes_count?: number;
        };
        puedeReportar: boolean;
        puedeGestionar: boolean;
        puedeCambiarEstado?: boolean;
        transicionesPermitidas?: string[];
    } = $props();

    function cambiarEstado(estado: string) {
        router.post(`/programas/${programa.id}/cambiar-estado`, { estado });
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

    const urlReportar = $derived(`/reportes/crear?programa=${programa.id}`);
    const enPausa = $derived(programa.estado === 'en_pausa');
</script>

<AppHead title={programa.nombre} />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
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
                        <span class="text-sm text-muted-foreground">Recompensa</span>
                        <span class="text-sm font-semibold text-primary">
                            {new Intl.NumberFormat('es-ES').format(programa.recompensa_min)}
                            {' - '}
                            {new Intl.NumberFormat('es-ES').format(programa.recompensa_max)}
                            {' '}{programa.moneda}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Reportes recibidos</span>
                        <span class="text-sm">{programa.reportes_count ?? 0}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">PoC requerido</span>
                        <span class="text-sm">{programa.requiere_poc ? 'Si' : 'No'}</span>
                    </div>

                    {#if programa.reputacion_minima > 0}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Reputación mínima</span>
                            <span class="text-sm">{programa.reputacion_minima}</span>
                        </div>
                    {/if}

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

            {#if puedeGestionar}
                <div class="flex flex-col gap-2 w-full">
                    <Button variant="outline" href={programaEdit(programa.id)} class="w-full">
                        <Edit class="mr-2 h-4 w-4" />
                        Editar Programa
                    </Button>
                    <Button variant="outline" href={gestionRoute()} class="w-full">
                        <Settings class="mr-2 h-4 w-4" />
                        Gestionar
                    </Button>
                </div>
            {/if}

            {#if puedeCambiarEstado && transicionesPermitidas.length > 0}
                <div class="flex flex-col gap-2 w-full">
                    {#each transicionesPermitidas as estado}
                        <Button
                            variant={estado === 'activo' ? 'default' : 'outline'}
                            class="w-full"
                            onclick={() => cambiarEstado(estado)}
                        >
                            {estado === 'activo' ? 'Publicar programa' : `Cambiar a ${estado}`}
                        </Button>
                    {/each}
                </div>
            {/if}
        </div>
    </div>
</div>
