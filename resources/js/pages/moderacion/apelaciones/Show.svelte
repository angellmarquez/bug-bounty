<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Apelaciones', href: '/moderacion/apelaciones' },
            { title: 'Detalle' },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import ApelacionTraza from '@/components/ApelacionTraza.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import ResolverApelacionForm from '@/components/ResolverApelacionForm.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import {
        estadoApelacionColor,
        estadoApelacionLabel,
        estadoSancionColor,
        estadoSancionLabel,
        gravedadSancionColor,
        gravedadSancionLabel,
    } from '@/lib/status-colors';
    import type { ApelacionParaResolver, PasoApelacion } from '@/types/domain';

    let {
        apelacion,
    }: {
        apelacion: ApelacionParaResolver & { eventos: PasoApelacion[]; cadena_valida: boolean };
    } = $props();

    function fecha(iso: string | null): string {
        if (!iso) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(iso));
    }
</script>

<AppHead title="Detalle de apelación" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver href="/moderacion/apelaciones" etiqueta="Volver a apelaciones" />
        <PageHeader
            title="Apelación #{apelacion.id} de {apelacion.usuario?.name ?? 'Desconocido'}"
            description="Presentada el {fecha(apelacion.created_at)}"
        />
        <span class={estadoApelacionColor(apelacion.estado)} data-test="estado-apelacion">
            {estadoApelacionLabel(apelacion.estado)}
        </span>
    </div>

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
                    <p data-test="sanciono">
                        <span class="text-muted-foreground">Aplicada por:</span>
                        <span class="font-medium">{apelacion.sancion.aplicada_por?.name ?? 'Sistema (auditoría automática)'}</span>
                        <span class="text-muted-foreground">
                            ({apelacion.sancion.origen === 'triaje' ? 'al rechazar un informe' : 'detección automática'})
                        </span>
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
                    <CardTitle>Motivo del investigador</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <p class="whitespace-pre-wrap">{apelacion.motivo}</p>
                    {#if apelacion.estado === 'pendiente'}
                        {#if apelacion.puede_resolver}
                            <ResolverApelacionForm apelacionId={apelacion.id} />
                        {:else}
                            <p class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs" data-test="bloqueo-resolver">
                                {apelacion.motivo_bloqueo}
                            </p>
                        {/if}
                    {/if}
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Registro de control</CardTitle>
            </CardHeader>
            <CardContent>
                <ApelacionTraza eventos={apelacion.eventos} cadenaValida={apelacion.cadena_valida} completa />
            </CardContent>
        </Card>
    </div>
</div>
