<script module lang="ts">
    import { index as reportesIndex } from '@/routes/reportes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reportes',
                href: reportesIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Timeline from '@/components/Timeline.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { index as reportesRoute } from '@/routes/reportes';
    import type { Reporte } from '@/types/domain';

    let {
        reporte,
        puedeVerNotasInternas,
    }: {
        reporte: Reporte;
        puedeVerNotasInternas: boolean;
    } = $props();

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

    function formatJson(obj: Record<string, unknown> | null): string {
        if (!obj) return '';
        return JSON.stringify(obj, null, 2);
    }
</script>

<AppHead title="{reporte.numero_reporte} — {reporte.titulo}" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" href={reportesRoute()}>
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <PageHeader
            title="{reporte.numero_reporte}"
            description={reporte.titulo}
        />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Descripcion</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-wrap text-sm">{reporte.descripcion}</p>
                </CardContent>
            </Card>

            {#if reporte.poc && Object.keys(reporte.poc).length > 0}
                <Card>
                    <CardHeader>
                        <CardTitle>Prueba de concepto (PoC)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre class="overflow-x-auto rounded-lg bg-muted p-4 text-xs">{formatJson(reporte.poc)}</pre>
                    </CardContent>
                </Card>
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
                                href={`/programas/${reporte.programa.slug}`}
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

                    {#if reporte.recompensa}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Recompensa</span>
                            <span class="text-sm font-semibold text-primary">
                                {reporte.recompensa.toLocaleString('es-ES', { minimumFractionDigits: 2 })} {reporte.moneda}
                            </span>
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

            {#if reporte.duplicadoDe}
                <Card>
                    <CardHeader>
                        <CardTitle>Duplicado de</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Link
                            href={`/reportes/${reporte.duplicadoDe.id}`}
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
        <CardContent>
            <Timeline eventos={reporte.eventos ?? []} />
        </CardContent>
    </Card>
</div>
