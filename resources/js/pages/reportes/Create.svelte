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
    import { Form, router } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import Send from '@lucide/svelte/icons/send';
    import Save from '@lucide/svelte/icons/save';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import WizardSteps from '@/components/WizardSteps.svelte';
    import CvssCalculator from '@/components/CvssCalculator.svelte';
    import PocForm from '@/components/PocForm.svelte';
    import PgpKeySelector from '@/components/PgpKeySelector.svelte';
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
    } from '@/components/ui/select';
    import { create as createRoute, store } from '@/routes/reportes';
    import type { PocSchemaField, Programa } from '@/types/domain';
    import type { Severidad } from '@/types/enums';
    import { schemaVacio, validarPoc } from '@/lib/poc-schema';

    let {
        programas = [],
        programaInicial = null,
        clavesPgp = [],
    }: {
        programas: Pick<Programa, 'id' | 'nombre' | 'slug' | 'poc_schema'>[];
        programaInicial: Pick<Programa, 'id' | 'nombre' | 'slug'> | null;
        clavesPgp: { id: number; huella: string; algoritmo: string | null; bits: number | null; es_principal: boolean }[];
    } = $props();

    let pasoActual = $state(1);
    let erroresPaso = $state<Record<string, string>>({});

    let formulario = $state({
        programa_id: programaInicial?.id ? String(programaInicial.id) : '',
        titulo: '',
        descripcion: '',
        categoria: '',
        vector_cvss: '',
        puntuacion_cvss: null as number | null,
        severidad: null as Severidad | null,
        poc: {} as Record<string, unknown>,
        clave_pgp_id: null as string | null,
    });

    let pocSchema = $derived<PocSchemaField[]>([]);

    let processing = $state(false);

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
            if (!formulario.titulo.trim()) erroresPaso.titulo = 'El titulo es obligatorio';
            if (!formulario.descripcion.trim()) erroresPaso.descripcion = 'La descripcion es obligatoria';
            if (!formulario.programa_id) erroresPaso.programa_id = 'Debe seleccionar un programa';
        } else if (pasoActual === 3 && pocSchema.length > 0) {
            erroresPaso = validarPoc(formulario.poc, pocSchema);
        }
        return Object.keys(erroresPaso).length === 0;
    }

    function siguientePaso() {
        if (validarPasoActual()) {
            if (pasoActual < 5) pasoActual++;
        }
    }

    function pasoAnterior() {
        if (pasoActual > 1) pasoActual--;
    }

    function enviarFormulario(e: Event) {
        processing = true;
    }
</script>

<AppHead title="Crear reporte" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" href={reportesRoute()}>
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <PageHeader
            title="Crear reporte"
            description="Complete los pasos para crear un nuevo reporte de vulnerabilidad"
        />
    </div>

    <WizardSteps pasos={['Detalles', 'CVSS', 'PoC', 'PGP', 'Revision']} {pasoActual} />

    <Form
        {...store.form()}
        options={{ preserveScroll: true }}
        class="space-y-6"
        on:submit={enviarFormulario}
    >
        {#snippet children({ errors: formErrors, processing: formProcessing })}
            {#if pasoActual === 1}
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="programa_id">Programa *</Label>
                            <Select
                                value={formulario.programa_id}
                                onValueChange={seleccionarPrograma}
                            >
                                <SelectTrigger class="w-full">
                                    <span>Seleccionar programa...</span>
                                </SelectTrigger>
                                <SelectContent>
                                    {#each programas as prog (prog.id)}
                                        <SelectItem value={String(prog.id)}>
                                            {prog.nombre}
                                        </SelectItem>
                                    {/each}
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="programa_id" value={formulario.programa_id} />
                            {#if erroresPaso.programa_id || formErrors.programa_id}
                                <InputError message={erroresPaso.programa_id ?? formErrors.programa_id} />
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="titulo">Titulo *</Label>
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
                            <Label for="descripcion">Descripcion *</Label>
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
                            <Label for="categoria">Categoria</Label>
                            <Select
                                value={formulario.categoria}
                                onValueChange={(v) => (formulario.categoria = v)}
                            >
                                <SelectTrigger class="w-full">
                                    <span>Seleccionar categoria...</span>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="xss">XSS</SelectItem>
                                    <SelectItem value="sql_injection">SQL Injection</SelectItem>
                                    <SelectItem value="rce">RCE</SelectItem>
                                    <SelectItem value="idor">IDOR</SelectItem>
                                    <SelectItem value="csrf">CSRF</SelectItem>
                                    <SelectItem value="ssrf">SSRF</SelectItem>
                                    <SelectItem value="xxe">XXE</SelectItem>
                                    <SelectItem value="otro">Otro</SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="categoria" value={formulario.categoria} />
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
                <input type="hidden" name="vector_cvss" value={formulario.vector_cvss} />
                <input type="hidden" name="puntuacion_cvss" value={formulario.puntuacion_cvss ?? ''} />
                <input type="hidden" name="severidad" value={formulario.severidad ?? ''} />

            {:else if pasoActual === 3}
                <PocForm
                    schema={pocSchema}
                    bind:data={formulario.poc}
                    bind:errors={erroresPaso}
                />
                <input type="hidden" name="poc" value={JSON.stringify(formulario.poc)} />

            {:else if pasoActual === 4}
                <PgpKeySelector
                    claves={clavesPgp}
                    bind:value={formulario.clave_pgp_id}
                />
                <input type="hidden" name="clave_pgp_id" value={formulario.clave_pgp_id ?? ''} />

            {:else if pasoActual === 5}
                <Card>
                    <CardHeader>
                        <CardTitle>Revision del reporte</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">Programa</p>
                                <p class="text-sm">{programas.find((p) => String(p.id) === formulario.programa_id)?.nombre ?? 'No seleccionado'}</p>
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

                        <input type="hidden" name="titulo" value={formulario.titulo} />
                        <input type="hidden" name="descripcion" value={formulario.descripcion} />
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
                    {#if pasoActual < 5}
                        <Button type="button" onclick={siguientePaso}>
                            Siguiente
                            <ArrowRight class="ml-1 h-4 w-4" />
                        </Button>
                    {:else}
                        <Button
                            type="submit"
                            disabled={formProcessing}
                            variant="outline"
                        >
                            {#if formProcessing}<Spinner />{/if}
                            <Save class="mr-1 h-4 w-4" />
                            Guardar borrador
                        </Button>
                    {/if}
                </div>
            </div>
        {/snippet}
    </Form>
</div>
