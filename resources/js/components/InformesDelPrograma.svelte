<script lang="ts">
    import Inbox from '@lucide/svelte/icons/inbox';
    import ReportesCompactos from '@/components/ReportesCompactos.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import type { ReporteCompacto } from '@/types/domain';

    type Filtro = 'por_revisar' | 'en_revision' | 'aprobados' | 'rechazados' | 'todos';

    let {
        programaId,
        filtro,
        conteos,
        informes,
    }: {
        programaId: number;
        filtro: Filtro;
        conteos: Record<Filtro, number>;
        informes: ReporteCompacto[];
    } = $props();

    const pestanas: { valor: Filtro; etiqueta: string }[] = [
        { valor: 'por_revisar', etiqueta: 'Por revisar' },
        { valor: 'en_revision', etiqueta: 'En revisión' },
        { valor: 'aprobados', etiqueta: 'Aprobados' },
        { valor: 'rechazados', etiqueta: 'Rechazados' },
        { valor: 'todos', etiqueta: 'Todos' },
    ];
</script>

<Card>
    <CardHeader>
        <CardTitle>Informes de este programa</CardTitle>
        <CardDescription>
            Vista rápida para revisar: abre "Vista rápida" en un informe para leerlo aquí mismo, con su
            descripción y su prueba de concepto.
        </CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
        <div class="flex flex-wrap gap-2">
            {#each pestanas as pestana (pestana.valor)}
                <Button
                    size="sm"
                    variant={filtro === pestana.valor ? 'default' : 'outline'}
                    href={`/programas/${programaId}?filtro=${pestana.valor}`}
                    preserveScroll
                >
                    {pestana.etiqueta} ({conteos[pestana.valor]})
                </Button>
            {/each}
        </div>

        {#if informes.length === 0}
            <div class="flex flex-col items-center gap-2 rounded-md border border-dashed py-10 text-center">
                <Inbox class="h-6 w-6 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No hay informes en esta vista.</p>
            </div>
        {:else}
            <ReportesCompactos
                reportes={informes}
                mostrarPrograma={false}
                vistaRapida
                destacar={(reporte) => reporte.estado === 'enviado'}
                etiquetaAccion={(reporte) =>
                    reporte.estado === 'enviado' || reporte.estado === 'en_revision' ? 'Revisar' : 'Ver'}
            />
        {/if}

        {#if conteos[filtro] > informes.length}
            <p class="text-xs text-muted-foreground">Mostrando los primeros {informes.length} de {conteos[filtro]}.</p>
        {/if}

        <Button variant="outline" size="sm" href={`/moderacion/programas/${programaId}?filtro=${filtro}`}>
            Ver todos en la cola de moderación
        </Button>
    </CardContent>
</Card>
