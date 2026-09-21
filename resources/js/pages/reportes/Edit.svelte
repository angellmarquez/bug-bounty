<script module lang="ts">
    import { index as reportesIndex, show as reportesShow } from '@/routes/reportes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reportes',
                href: reportesIndex(),
            },
            {
                title: 'Detalles',
                href: '#',
            },
            {
                title: 'Editar reporte',
                href: '#',
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import Save from '@lucide/svelte/icons/save';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import WizardSteps from '@/components/WizardSteps.svelte';
    import CvssCalculator from '@/components/CvssCalculator.svelte';
    import PocForm from '@/components/PocForm.svelte';
    import AlertError from '@/components/AlertError.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import { edit, update } from '@/routes/reportes';
    import type { PocSchemaField, Reporte } from '@/types/domain';
    import type { Severidad } from '@/types/enums';
    import { validarPoc } from '@/lib/poc-schema';
    import { CATEGORIAS_REPORTE } from '@/lib/categorias-reporte';

    let {
        reporte,
    }: {
        reporte: Reporte & { programa: { id: number; nombre: string; slug: string; poc_schema: PocSchemaField[] | null } };
    } = $props();

    let pasoActual = $state(1);
    let erroresPaso = $state<Record<string, string>>({});

    const esEnviado = $derived(reporte.estado === 'enviado');
    const maxPaso = $derived(esEnviado ? 3 : 4);

    let formulario = $state({
        titulo: reporte.titulo,
        descripcion: reporte.descripcion,
        categoria: reporte.categoria ?? '',
        vector_cvss: reporte.vector_cvss ?? '',
        puntuacion_cvss: reporte.puntuacion_cvss,
        severidad: reporte.severidad,
        poc: (reporte.poc ?? {}) as Record<string, unknown>,
    });

    let pocSchema = $derived<PocSchemaField[]>(reporte.programa?.poc_schema ?? []);

    function validarPasoActual(): boolean {
        erroresPaso = {};
        if (pasoActual === 1) {
            if (!formulario.titulo.trim()) erroresPaso.titulo = 'El titulo es obligatorio';
            if (!formulario.descripcion.trim()) erroresPaso.descripcion = 'La descripcion es obligatoria';
        } else if (pasoActual === 3 && pocSchema.length > 0) {
            erroresPaso = validarPoc(formulario.poc, pocSchema);
        }
        return Object.keys(erroresPaso).length === 0;
    }

    function siguientePaso() {
        if (validarPasoActual() && pasoActual < maxPaso) {
            pasoActual++;
        }
    }

    function pasoAnterior() {
        if (pasoActual > 1) pasoActual--;
    }

    // El payload sale del estado del wizard y no del DOM: los pasos anteriores
    // ya están desmontados cuando se llega al paso final.
    function construirPayload() {
        const { titulo, descripcion, categoria, vector_cvss, puntuacion_cvss, severidad, poc } =
            $state.snapshot(formulario);

        return esEnviado
            ? { titulo, descripcion, poc }
            : { titulo, descripcion, categoria, vector_cvss, puntuacion_cvss, severidad, poc };
    }

    // Enter en un campo de un paso intermedio avanza el wizard en lugar de enviar.
    function alEnviar() {
        if (pasoActual < maxPaso) {
            siguientePaso();
            return false;
        }
    }
</script>

<AppHead title="Editar reporte — {reporte.numero_reporte}" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" href={reportesShow(reporte.id)}>
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <PageHeader
            title="Editar {reporte.numero_reporte}"
            description={esEnviado ? 'Editando reporte enviado (solo titulo, descripcion y PoC)' : 'Edite los campos del reporte'}
        />
    </div>

    <WizardSteps
        pasos={esEnviado ? ['Detalles', 'CVSS', 'PoC'] : ['Detalles', 'CVSS', 'PoC', 'Revision']}
        {pasoActual}
    />

    <Form
        {...update(reporte.id)}
        method="put"
        options={{ preserveScroll: true }}
        class="space-y-6"
        transform={(data) => ({ ...data, ...construirPayload() })}
        onBefore={alEnviar}
    >
        {#snippet children({ errors: formErrors, processing: formProcessing })}
            {#if formErrors.pgp}
                <AlertError errors={[formErrors.pgp]} title="No se pudo guardar el reporte" />
            {/if}
            {#if pasoActual === 1}
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label>Programa</Label>
                            <p class="text-sm text-muted-foreground">{reporte.programa?.nombre ?? 'N/A'}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="titulo">Titulo *</Label>
                            <Input
                                id="titulo"
                                name="titulo"
                                bind:value={formulario.titulo}
                                required
                                disabled={esEnviado}
                            />
                            {#if erroresPaso.titulo || formErrors.titulo}
                                <InputError message={erroresPaso.titulo ?? formErrors.titulo} />
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="descripcion">Descripcion *</Label>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                bind:value={formulario.descripcion}
                                rows="6"
                                required
                                class="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            {#if erroresPaso.descripcion || formErrors.descripcion}
                                <InputError message={erroresPaso.descripcion ?? formErrors.descripcion} />
                            {/if}
                        </div>

                        {#if !esEnviado}
                            <div class="space-y-2">
                                <Label for="categoria">Categoria</Label>
                                <Select
                                    value={formulario.categoria}
                                    onValueChange={(v) => (formulario.categoria = v)}
                                    items={CATEGORIAS_REPORTE}
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Seleccionar categoria..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each CATEGORIAS_REPORTE as categoria (categoria.value)}
                                            <SelectItem value={categoria.value} label={categoria.label}>
                                                {categoria.label}
                                            </SelectItem>
                                        {/each}
                                    </SelectContent>
                                </Select>
                            </div>
                        {/if}
                    </CardContent>
                </Card>

            {:else if pasoActual === 2}
                {#if esEnviado}
                    <Card>
                        <CardContent class="pt-6">
                            <p class="text-sm text-muted-foreground">CVSS: {reporte.vector_cvss ?? 'No calculado'} ({reporte.puntuacion_cvss ?? 'N/A'})</p>
                        </CardContent>
                    </Card>
                {:else}
                    <CvssCalculator
                        vector={formulario.vector_cvss}
                        onChange={(vector, score, sev) => {
                            formulario.vector_cvss = vector;
                            formulario.puntuacion_cvss = score;
                            formulario.severidad = sev;
                        }}
                    />
                {/if}

            {:else if pasoActual === 3}
                <PocForm
                    schema={pocSchema}
                    bind:data={formulario.poc}
                    bind:errors={erroresPaso}
                />

            {:else if pasoActual === 4}
                <Card>
                    <CardHeader>
                        <CardTitle>Revision del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">Programa</p>
                                <p class="text-sm">{reporte.programa?.nombre ?? 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Titulo</p>
                                <p class="text-sm">{formulario.titulo || 'Sin titulo'}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-muted-foreground">Descripcion</p>
                            <p class="whitespace-pre-wrap text-sm">{formulario.descripcion || 'Sin descripcion'}</p>
                        </div>

                        {#if formulario.vector_cvss}
                            <div>
                                <p class="text-xs text-muted-foreground">CVSS</p>
                                <p class="text-sm">{formulario.vector_cvss} ({formulario.puntuacion_cvss} - {formulario.severidad})</p>
                            </div>
                        {/if}

                        {#if Object.keys(formulario.poc).length > 0}
                            <div>
                                <p class="text-xs text-muted-foreground">PoC</p>
                                <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{JSON.stringify(formulario.poc, null, 2)}</pre>
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            {/if}

            <div class="flex items-center justify-between">
                <Button
                    type="button"
                    variant="outline"
                    onclick={pasoAnterior}
                    disabled={pasoActual === 1}
                >
                    <ArrowLeft class="mr-1 h-4 w-4" />
                    Anterior
                </Button>

                <div class="flex items-center gap-2">
                    {#if pasoActual < maxPaso}
                        <Button type="button" onclick={siguientePaso}>
                            Siguiente
                            <ArrowRight class="ml-1 h-4 w-4" />
                        </Button>
                    {:else}
                        <Button type="submit" disabled={formProcessing}>
                            {#if formProcessing}<Spinner />{/if}
                            <Save class="mr-1 h-4 w-4" />
                            Actualizar borrador
                        </Button>
                    {/if}
                </div>
            </div>
        {/snippet}
    </Form>
</div>
