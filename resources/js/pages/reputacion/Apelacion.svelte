<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Mi reputación', href: '/reputacion' },
            { title: 'Apelaciones', href: '/reputacion/apelaciones' },
            { title: 'Seguimiento' },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import ApelacionTraza from '@/components/ApelacionTraza.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import {
        estadoApelacionColor,
        estadoApelacionLabel,
        estadoSancionColor,
        estadoSancionLabel,
        gravedadSancionColor,
        gravedadSancionLabel,
    } from '@/lib/status-colors';
    import type { PasoApelacion, Sancion } from '@/types/domain';
    import type { EstadoApelacion } from '@/types/enums';

    let {
        apelacion,
    }: {
        apelacion: {
            id: number;
            estado: EstadoApelacion;
            motivo: string;
            nota_resolucion: string | null;
            created_at: string;
            resuelta_en: string | null;
            sancion: Pick<Sancion, 'id' | 'motivo' | 'gravedad' | 'puntos' | 'estado' | 'plazo_apelacion'> & {
                aplicada_por_rol: string;
                reporte: { id: number; numero_reporte: string; titulo: string } | null;
            };
            eventos: PasoApelacion[];
            cadena_valida: boolean;
        };
    } = $props();

    const MENSAJES: Record<EstadoApelacion, string> = {
        pendiente: 'Tu apelación está pendiente: un moderador o el administrador la revisará. Te avisaremos del resultado aquí.',
        aprobada: 'Apelación aprobada: la sanción se revocó, se te devolvieron los puntos y se levantó la suspensión.',
        rechazada: 'Apelación rechazada: la sanción sigue vigente. Cada sanción se puede apelar una sola vez.',
    };

    const QUIEN_SANCIONO: Record<string, string> = {
        moderador: 'Un moderador',
        administrador: 'Un administrador',
        sistema: 'El sistema (auditoría automática)',
    };

    function fecha(iso: string | null): string {
        if (!iso) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(iso));
    }
</script>

<AppHead title="Seguimiento de apelación" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver href="/reputacion/apelaciones" etiqueta="Volver a mis apelaciones" />
        <PageHeader title="Seguimiento de la apelación #{apelacion.id}" description="Presentada el {fecha(apelacion.created_at)}" />
    </div>

    <Card>
        <CardContent class="flex flex-col gap-3 pt-6 sm:flex-row sm:items-center">
            <span class={estadoApelacionColor(apelacion.estado)} data-test="estado-apelacion">
                {estadoApelacionLabel(apelacion.estado)}
            </span>
            <p class="text-sm" data-test="mensaje-estado">{MENSAJES[apelacion.estado]}</p>
        </CardContent>
    </Card>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>La sanción</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class={gravedadSancionColor(apelacion.sancion.gravedad)}>
                            {gravedadSancionLabel(apelacion.sancion.gravedad)}
                        </span>
                        <span class={estadoSancionColor(apelacion.sancion.estado)}>
                            {estadoSancionLabel(apelacion.sancion.estado)}
                        </span>
                        <span class="text-muted-foreground">{apelacion.sancion.puntos} puntos</span>
                    </div>
                    <p>{apelacion.sancion.motivo}</p>
                    <p class="text-muted-foreground">
                        Aplicada por: <span class="text-foreground">{QUIEN_SANCIONO[apelacion.sancion.aplicada_por_rol] ?? apelacion.sancion.aplicada_por_rol}</span>
                    </p>
                    <p class="text-muted-foreground">Plazo para apelar: {fecha(apelacion.sancion.plazo_apelacion)}</p>
                    {#if apelacion.sancion.reporte}
                        <p>
                            Informe:
                            <a href="/reportes/{apelacion.sancion.reporte.id}" class="text-primary hover:underline">
                                {apelacion.sancion.reporte.numero_reporte} · {apelacion.sancion.reporte.titulo}
                            </a>
                        </p>
                    {/if}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Tu motivo</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <p class="whitespace-pre-wrap">{apelacion.motivo}</p>
                    {#if apelacion.nota_resolucion}
                        <div class="rounded-md bg-muted/50 px-3 py-2">
                            <p class="text-xs text-muted-foreground">Resolución ({fecha(apelacion.resuelta_en)})</p>
                            <p class="whitespace-pre-wrap" data-test="nota-resolucion">{apelacion.nota_resolucion}</p>
                        </div>
                    {/if}
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Registro de la apelación</CardTitle>
            </CardHeader>
            <CardContent>
                <ApelacionTraza eventos={apelacion.eventos} cadenaValida={apelacion.cadena_valida} />
            </CardContent>
        </Card>
    </div>
</div>
