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
    import { Link, router } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
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
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Separator } from '@/components/ui/separator';
    import { show as programaShow, gestion as gestionRoute } from '@/routes/programas';
    import type { Programa, ObjetivoPrograma, PocSchemaField } from '@/types/domain';

    let {
        programa,
        puedeReportar,
        puedeGestionar,
        puedeCambiarEstado = false,
        transicionesPermitidas = [],
    }: {
        programa: Programa & {
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
</script>

<AppHead title={programa.nombre} />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <PageHeader
            title={programa.nombre}
        />
        <ProgramaStateBadge estado={programa.estado} />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Descripcion</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-wrap text-sm">{programa.descripcion}</p>
                </CardContent>
            </Card>

            {#if programa.objetivos && programa.objetivos.length > 0}
                <Card>
                    <CardHeader>
                        <CardTitle>Objetivos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-2">
                            {#each programa.objetivos as obj (obj.id)}
                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        {tipoObjetivoLabel(obj.tipo)}
                                    </Badge>
                                    <span class="text-sm text-foreground">{obj.valor}</span>
                                </div>
                            {/each}
                        </div>
                    </CardContent>
                </Card>
            {/if}

            {#if programa.poc_schema && programa.poc_schema.length > 0}
                <Card>
                    <CardHeader>
                        <CardTitle>Campos PoC requeridos</CardTitle>
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
                        <span class="text-sm text-muted-foreground">Reportes</span>
                        <span class="text-sm">{programa.reportes_count ?? 0}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">PoC requerido</span>
                        <span class="text-sm">{programa.requiere_poc ? 'Si' : 'No'}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Publico</span>
                        <span class="text-sm">{programa.es_publico ? 'Si' : 'No'}</span>
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

            {#if puedeReportar}
                <Button href={`/reportes/crear?programa=${programa.id}`} class="w-full">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    Reportar
                </Button>
            {/if}

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
