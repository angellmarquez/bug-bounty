<script module lang="ts">
    import { create } from '@/routes/reportes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reportes',
                href: '/reportes',
            },
            {
                title: 'Crear reporte',
                href: create(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import Save from '@lucide/svelte/icons/save';
    import Send from '@lucide/svelte/icons/send';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import type { CuentaEstado } from '@/lib/rangos';
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
    import { index as reportesIndex, create as createRoute, store } from '@/routes/reportes';
    import type { PocSchemaField, Programa } from '@/types/domain';
    import type { Severidad } from '@/types/enums';
    import { schemaVacio, validarPoc } from '@/lib/poc-schema';
    import { CATEGORIAS_REPORTE } from '@/lib/categorias-reporte';

    let {
        programas = [],
        programaInicial = null,
    }: {
        programas: Pick<Programa, 'id' | 'nombre' | 'slug' | 'poc_schema'>[];
        programaInicial: Pick<Programa, 'id' | 'nombre' | 'slug'> | null;
    } = $props();

    let pasoActual = $state(1);
    let erroresPaso = $state<Record<string, string>>({});

    // Una suspensión vigente impide enviar informes: se avisa antes de que rellene todo el formulario.
    const suspension = $derived((page.props.cuenta as CuentaEstado | null | undefined)?.suspension ?? null);

    let formulario = $state({
        programa_id: programaInicial?.id ? String(programaInicial.id) : '',
        titulo: '',
        descripcion: '',
        categoria: '',
        vector_cvss: '',
        puntuacion_cvss: null as number | null,
        severidad: null as Severidad | null,
        poc: {} as Record<string, unknown>,
    });

    const schemaInicial = programas.find((p) => String(p.id) === formulario.programa_id)?.poc_schema ?? [];
    let pocSchema = $state<PocSchemaField[]>(schemaInicial);
    formulario.poc = schemaVacio(schemaInicial);

    function seleccionarPrograma(id: string) {
        formulario.programa_id = id;
        const prog = programas.find((p) => String(p.id) === id);
        if (prog?.poc_schema) {
            pocSchema = prog.poc_schema;
            formulario.poc = schemaVacio(prog.poc_schema);
        } else {
            pocSchema = [];
            formulario.poc = {};
        }
    }

    function validarPasoActual(): boolean {
        erroresPaso = {};
        if (pasoActual === 1) {
            if (!formulario.titulo.trim()) erroresPaso.titulo = 'El título es obligatorio';
            if (!formulario.descripcion.trim()) erroresPaso.descripcion = 'La descripción es obligatoria';
            if (!formulario.programa_id) erroresPaso.programa_id = 'Debe seleccionar un programa';
        } else if (pasoActual === 3 && pocSchema.length > 0) {
            erroresPaso = validarPoc(formulario.poc, pocSchema);
        }
        return Object.keys(erroresPaso).length === 0;
    }

    function siguientePaso() {
        if (validarPasoActual()) {
            if (pasoActual < 4) pasoActual++;
        }
    }

    function pasoAnterior() {
        if (pasoActual > 1) pasoActual--;
    }

    // El payload sale del estado del wizard y no del DOM: los pasos anteriores
    // ya están desmontados cuando se llega al paso final.
    // Qué botón del último paso se pulsó: guardar solo o guardar y enviar al programa.
    let enviarAlGuardar = $state(false);

    function construirPayload() {
        return { ...$state.snapshot(formulario), enviar: enviarAlGuardar };
    }

    // Enter en un campo de un paso intermedio avanza el wizard en lugar de enviar.
    function alEnviar() {
        if (pasoActual < 4) {
            siguientePaso();
            return false;
        }
    }
</script>

<AppHead title="Crear reporte" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver href={reportesIndex()} etiqueta="Volver a mis reportes" />
        <PageHeader
            title="Crear reporte"
            description={programaInicial
                ? `Reporte de vulnerabilidad para el programa ${programaInicial.nombre}`
                : 'Complete los pasos para crear un nuevo reporte de vulnerabilidad'}
        />
    </div>

    {#if suspension}
        <div class="rounded-md border border-rose-500/40 bg-rose-500/10 p-3 text-sm" role="alert" data-test="aviso-suspension">
            <p class="font-medium">
                Tu cuenta está suspendida{suspension.hasta ? ` hasta el ${new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(suspension.hasta))}` : ''}.
            </p>
            <p class="text-muted-foreground">
                Hasta entonces no puedes crear ni enviar informes. Puedes seguir usando la plataforma y apelar la sanción desde Mi reputación.
            </p>
        </div>
    {/if}

    <WizardSteps pasos={['Detalles', 'CVSS', 'PoC', 'Revisión']} {pasoActual} />

    <Form
        {...store.form()}
        options={{ preserveScroll: true }}
        class="space-y-6"
        transform={(data) => ({ ...data, ...construirPayload() })}
        onBefore={alEnviar}
    >
        {#snippet children({ errors: formErrors, processing: formProcessing })}
            {#if formErrors.pgp}
                <AlertError errors={[formErrors.pgp]} title="No se pudo guardar el reporte" />
            {/if}
            {#if formErrors.limite}
                <div data-test="aviso-limite" class="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
                    <p class="font-medium">No se pudo enviar el informe</p>
                    <p class="text-muted-foreground">{formErrors.limite}</p>
                    <p class="mt-1 text-muted-foreground">Puedes guardarlo como borrador y enviarlo más tarde.</p>
                </div>
            {/if}
            {#if pasoActual === 1}
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        {#if programaInicial}
                            <!-- Se llegó desde un programa: el reporte es sobre ese programa, no se elige. -->
                            <div class="space-y-1" data-test="programa-fijo">
                                <p class="text-sm font-medium">Programa</p>
                                <p class="rounded-md border bg-muted/40 px-3 py-2 text-sm">{programaInicial.nombre}</p>
                                {#if formErrors.programa_id}
                                    <InputError message={formErrors.programa_id} />
                                {/if}
                            </div>
                        {:else}
                            <div class="space-y-2">
                                <Label for="programa_id">Programa *</Label>
                                <Select
                                    value={formulario.programa_id}
                                    onValueChange={seleccionarPrograma}
                                    items={programas.map((p) => ({ value: String(p.id), label: p.nombre }))}
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Seleccionar programa..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each programas as prog (prog.id)}
                                            <SelectItem value={String(prog.id)} label={prog.nombre}>
                                                {prog.nombre}
                                            </SelectItem>
                                        {/each}
                                    </SelectContent>
                                </Select>
                                {#if erroresPaso.programa_id || formErrors.programa_id}
                                    <InputError message={erroresPaso.programa_id ?? formErrors.programa_id} />
                                {/if}
                            </div>
                        {/if}

                        <div class="space-y-2">
                            <Label for="titulo">Título *</Label>
                            <Input
                                id="titulo"
                                name="titulo"
                                bind:value={formulario.titulo}
                                placeholder="Ej: XSS en formulario de login"
                                required
                            />
                            {#if erroresPaso.titulo || formErrors.titulo}
                                <InputError message={erroresPaso.titulo ?? formErrors.titulo} />
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="descripcion">Descripción *</Label>
                            <textarea
                                id="descripcion"
                                name="descripcion"
                                bind:value={formulario.descripcion}
                                placeholder="Describe la vulnerabilidad encontrada, incluyendo pasos para reproducirla..."
                                rows="6"
                                required
                                class="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            {#if erroresPaso.descripcion || formErrors.descripcion}
                                <InputError message={erroresPaso.descripcion ?? formErrors.descripcion} />
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="categoria">Categoría</Label>
                            <Select
                                value={formulario.categoria}
                                onValueChange={(v) => (formulario.categoria = v)}
                                items={CATEGORIAS_REPORTE}
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Seleccionar categoría..." />
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
                    </CardContent>
                </Card>

            {:else if pasoActual === 2}
                <CvssCalculator
                    vector={formulario.vector_cvss}
                    onChange={(vector, score, sev) => {
                        formulario.vector_cvss = vector;
                        formulario.puntuacion_cvss = score;
                        formulario.severidad = sev;
                    }}
                />

            {:else if pasoActual === 3}
                <PocForm
                    schema={pocSchema}
                    bind:data={formulario.poc}
                    bind:errors={erroresPaso}
                />

            {:else if pasoActual === 4}
                <Card>
                    <CardHeader>
                        <CardTitle>Revisión del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">Programa</p>
                                <p class="text-sm">{programas.find((p) => String(p.id) === formulario.programa_id)?.nombre ?? 'No seleccionado'}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Título</p>
                                <p class="text-sm">{formulario.titulo || 'Sin título'}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-muted-foreground">Descripción</p>
                            <p class="whitespace-pre-wrap text-sm">{formulario.descripcion || 'Sin descripción'}</p>
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
                    {#if pasoActual < 4}
                        <Button type="button" onclick={siguientePaso}>
                            Siguiente
                            <ArrowRight class="ml-1 h-4 w-4" />
                        </Button>
                    {:else}
                        <Button
                            type="submit"
                            disabled={formProcessing || !!suspension}
                            variant="outline"
                            onclick={() => (enviarAlGuardar = false)}
                        >
                            {#if formProcessing && !enviarAlGuardar}<Spinner />{/if}
                            <Save class="mr-1 h-4 w-4" />
                            Guardar borrador
                        </Button>
                        <Button
                            type="submit"
                            disabled={formProcessing || !!suspension}
                            onclick={() => (enviarAlGuardar = true)}
                        >
                            {#if formProcessing && enviarAlGuardar}<Spinner />{/if}
                            <Send class="mr-1 h-4 w-4" />
                            Guardar y enviar
                        </Button>
                    {/if}
                </div>
            </div>
        {/snippet}
    </Form>
</div>
