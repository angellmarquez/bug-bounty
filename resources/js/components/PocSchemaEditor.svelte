<script lang="ts">
    import Zap from '@lucide/svelte/icons/zap';
    import Globe from '@lucide/svelte/icons/globe';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import InputError from '@/components/InputError.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import type { PocSchemaField, PocSchemaFieldType } from '@/types/domain';

    const TIPOS_CAMPO: { value: PocSchemaFieldType; label: string }[] = [
        { value: 'text', label: 'Texto corto' },
        { value: 'textarea', label: 'Texto largo' },
        { value: 'select', label: 'Selección (opciones)' },
        { value: 'number', label: 'Número' },
        { value: 'url', label: 'URL' },
        { value: 'code', label: 'Código / payload' },
    ];

    type CampoEditor = {
        name: string;
        label: string;
        type: PocSchemaFieldType;
        required: boolean;
        repeatable: boolean;
        placeholder: string;
        help: string;
        defaultValue: string;
        options: { value: string; label: string }[];
    };

    function aEditor(campo: PocSchemaField): CampoEditor {
        return {
            name: campo.name,
            label: campo.label,
            type: campo.type,
            required: campo.required ?? false,
            repeatable: campo.repeatable ?? false,
            placeholder: campo.placeholder ?? '',
            help: campo.help ?? '',
            defaultValue: campo.defaultValue ?? '',
            options: campo.options ? campo.options.map((o) => ({ ...o })) : [],
        };
    }

    let {
        schema = [],
        errors = {},
    }: {
        schema?: PocSchemaField[];
        errors?: Record<string, string>;
    } = $props();

    let campos = $state<CampoEditor[]>(schema.map(aEditor));

    function agregarCampo() {
        campos = [
            ...campos,
            {
                name: '',
                label: '',
                type: 'text',
                required: false,
                repeatable: false,
                placeholder: '',
                help: '',
                defaultValue: '',
                options: [],
            },
        ];
    }

    function eliminarCampo(index: number) {
        campos = campos.filter((_, i) => i !== index);
    }

    function agregarOpcion(index: number) {
        campos[index].options = [...campos[index].options, { value: '', label: '' }];
    }

    function eliminarOpcion(index: number, opcionIndex: number) {
        campos[index].options = campos[index].options.filter((_, i) => i !== opcionIndex);
    }

    function errorDe(campoIndex: number, sufijo: string): string | undefined {
        return errors[`poc_schema.${campoIndex}.${sufijo}`];
    }

    function cargarPlantillaWeb() {
        campos = [
            {
                name: 'url_afectada',
                label: 'URL o Endpoint Afectado',
                type: 'url',
                required: true,
                repeatable: false,
                placeholder: 'https://ejemplo.com/vulnerable-endpoint',
                help: 'Dirección web completa donde se reproduce la vulnerabilidad',
                defaultValue: '',
                options: [],
            },
            {
                name: 'pasos_reproducir',
                label: 'Pasos para Reproducir',
                type: 'text',
                required: true,
                repeatable: true,
                placeholder: 'Paso detallado para replicar el fallo',
                help: 'Indica los pasos numerados exactos',
                defaultValue: '',
                options: [],
            },
            {
                name: 'payload_request',
                label: 'Petición HTTP / Payload de Prueba',
                type: 'code',
                required: false,
                repeatable: false,
                placeholder: "POST /login HTTP/1.1\nHost: ejemplo.com\n\n' OR '1'='1",
                help: 'Payload utilizado o captura de la petición HTTP',
                defaultValue: '',
                options: [],
            },
            {
                name: 'impacto',
                label: 'Impacto Demostrable',
                type: 'textarea',
                required: true,
                repeatable: false,
                placeholder: 'Describe el impacto para los usuarios o el negocio si un atacante explota este fallo...',
                help: 'Consecuencias de seguridad reales',
                defaultValue: '',
                options: [],
            },
            {
                name: 'mitigacion',
                label: 'Recomendación de Mitigación',
                type: 'textarea',
                required: false,
                repeatable: false,
                placeholder: 'Cómo se recomienda solucionar la vulnerabilidad...',
                help: 'Recomendación técnica para el equipo de desarrollo',
                defaultValue: '',
                options: [],
            },
        ];
    }

    function cargarPlantillaApi() {
        campos = [
            {
                name: 'endpoint',
                label: 'Endpoint de la API',
                type: 'text',
                required: true,
                repeatable: false,
                placeholder: '/api/v1/usuarios/{id}',
                help: 'Ruta del endpoint vulnerable',
                defaultValue: '',
                options: [],
            },
            {
                name: 'metodo_http',
                label: 'Método HTTP',
                type: 'select',
                required: true,
                repeatable: false,
                placeholder: 'Selecciona método HTTP',
                help: 'Método utilizado en la petición',
                defaultValue: 'GET',
                options: [
                    { value: 'GET', label: 'GET' },
                    { value: 'POST', label: 'POST' },
                    { value: 'PUT', label: 'PUT' },
                    { value: 'DELETE', label: 'DELETE' },
                    { value: 'PATCH', label: 'PATCH' },
                ],
            },
            {
                name: 'pasos_reproducir',
                label: 'Pasos para Reproducir',
                type: 'text',
                required: true,
                repeatable: true,
                placeholder: 'Paso para replicar en la API',
                help: 'Pasos exactos para replicar la petición',
                defaultValue: '',
                options: [],
            },
            {
                name: 'request_curl',
                label: 'Comando cURL / Payload',
                type: 'code',
                required: true,
                repeatable: false,
                placeholder: "curl -X POST https://api.ejemplo.com/v1/... -H 'Authorization: Bearer ...'",
                help: 'Comando reproducible listo para terminal o Burp Suite',
                defaultValue: '',
                options: [],
            },
            {
                name: 'respuesta_servidor',
                label: 'Respuesta Obtenida del Servidor',
                type: 'code',
                required: false,
                repeatable: false,
                placeholder: 'HTTP/1.1 200 OK\n{\n  "error": false,\n  "datos_sensibles": "..."\n}',
                help: 'Respuesta devuelta por la API que evidencia la vulnerabilidad',
                defaultValue: '',
                options: [],
            },
            {
                name: 'impacto',
                label: 'Impacto de Seguridad',
                type: 'textarea',
                required: true,
                repeatable: false,
                placeholder: 'Acceso no autorizado a registros ajenos (IDOR), ejecución de código, etc.',
                help: 'Riesgo y consecuencia técnica',
                defaultValue: '',
                options: [],
            },
        ];
    }
</script>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold">Campos de la Prueba de Concepto (PoC)</h3>
            <p class="text-xs text-muted-foreground">
                Define los campos que el investigador debe completar al reportar (ej: endpoint, payload, pasos). Si no agregas ninguno, se usará un campo de texto libre.
            </p>
        </div>
        <Button type="button" variant="outline" size="sm" onclick={agregarCampo}>
            <Plus class="mr-1 h-4 w-4" />
            Agregar campo
        </Button>
    </div>

    <div class="flex flex-wrap items-center gap-2 rounded-md border border-dashed border-border p-2.5 bg-muted/20">
        <span class="text-xs font-medium text-muted-foreground">Plantillas rápidas:</span>
        <Button type="button" variant="secondary" size="sm" class="h-7 text-xs" onclick={cargarPlantillaWeb}>
            <Globe class="mr-1 size-3.5" /> Web Estándar (OWASP)
        </Button>
        <Button type="button" variant="secondary" size="sm" class="h-7 text-xs" onclick={cargarPlantillaApi}>
            <Zap class="mr-1 size-3.5" /> API REST / Backend
        </Button>
        {#if campos.length > 0}
            <Button type="button" variant="ghost" size="sm" class="h-7 text-xs text-muted-foreground ml-auto" onclick={() => (campos = [])}>
                Limpiar campos
            </Button>
        {/if}
    </div>

    {#if campos.length === 0}
        <EmptyState
            title="Sin campos personalizados"
            description="El formulario de PoC quedará libre (un solo campo de texto). Agrega campos si quieres estructurar la evidencia que pide este programa."
        />
    {:else}
        {#each campos as campo, i (i)}
            <div class="space-y-3 rounded-lg border border-border p-4">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xs font-medium text-muted-foreground">Campo #{i + 1}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Quitar campo"
                        onclick={() => eliminarCampo(i)}
                        class="h-7 w-7 shrink-0"
                    >
                        <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                </div>

                <input type="hidden" name={`poc_schema[${i}][name]`} value={campo.name} />
                <input type="hidden" name={`poc_schema[${i}][label]`} value={campo.label} />
                <input type="hidden" name={`poc_schema[${i}][type]`} value={campo.type} />
                <input type="hidden" name={`poc_schema[${i}][placeholder]`} value={campo.placeholder} />
                <input type="hidden" name={`poc_schema[${i}][help]`} value={campo.help} />
                <input type="hidden" name={`poc_schema[${i}][defaultValue]`} value={campo.defaultValue} />
                <input type="hidden" name={`poc_schema[${i}][required]`} value={campo.required ? '1' : '0'} />
                <input type="hidden" name={`poc_schema[${i}][repeatable]`} value={campo.repeatable ? '1' : '0'} />

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for={`poc-name-${i}`}>Identificador *</Label>
                        <Input
                            id={`poc-name-${i}`}
                            placeholder="ej: endpoint_afectado"
                            bind:value={campo.name}
                        />
                        <p class="text-[11px] text-muted-foreground">Sin espacios; se usa como clave interna del dato.</p>
                        <InputError message={errorDe(i, 'name')} />
                    </div>

                    <div class="space-y-1">
                        <Label for={`poc-label-${i}`}>Etiqueta visible *</Label>
                        <Input
                            id={`poc-label-${i}`}
                            placeholder="ej: Endpoint afectado"
                            bind:value={campo.label}
                        />
                        <InputError message={errorDe(i, 'label')} />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for={`poc-type-${i}`}>Tipo de campo</Label>
                        <Select
                            value={campo.type}
                            onValueChange={(v) => { campo.type = v as PocSchemaFieldType; }}
                            items={TIPOS_CAMPO}
                        >
                            <SelectTrigger id={`poc-type-${i}`}>
                                <SelectValue placeholder="Tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                {#each TIPOS_CAMPO as tipo (tipo.value)}
                                    <SelectItem value={tipo.value} label={tipo.label}>
                                        {tipo.label}
                                    </SelectItem>
                                {/each}
                            </SelectContent>
                        </Select>
                        <InputError message={errorDe(i, 'type')} />
                    </div>

                    <div class="space-y-1">
                        <Label for={`poc-default-${i}`}>Valor por defecto</Label>
                        <Input
                            id={`poc-default-${i}`}
                            placeholder="(opcional)"
                            bind:value={campo.defaultValue}
                        />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for={`poc-placeholder-${i}`}>Placeholder</Label>
                        <Input
                            id={`poc-placeholder-${i}`}
                            placeholder="Texto de ejemplo dentro del campo"
                            bind:value={campo.placeholder}
                        />
                    </div>

                    <div class="space-y-1">
                        <Label for={`poc-help-${i}`}>Texto de ayuda</Label>
                        <Input
                            id={`poc-help-${i}`}
                            placeholder="Aclaración que verá el investigador"
                            bind:value={campo.help}
                        />
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <Label class="flex items-center space-x-3">
                        <Checkbox bind:checked={campo.required} />
                        <span>Obligatorio</span>
                    </Label>
                    <Label class="flex items-center space-x-3">
                        <Checkbox bind:checked={campo.repeatable} />
                        <span>Repetible (varios valores)</span>
                    </Label>
                </div>

                {#if campo.type === 'select'}
                    <div class="space-y-2 rounded-md bg-muted/50 p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium">Opciones de selección *</span>
                            <Button type="button" variant="outline" size="sm" onclick={() => agregarOpcion(i)} class="h-7 text-xs">
                                <Plus class="mr-1 h-3 w-3" />
                                Opción
                            </Button>
                        </div>
                        {#if campo.options.length === 0}
                            <p class="text-[11px] text-muted-foreground">Agrega al menos una opción para este campo.</p>
                        {/if}
                        {#each campo.options as opcion, j (j)}
                            <div class="flex items-center gap-2">
                                <input type="hidden" name={`poc_schema[${i}][options][${j}][value]`} value={opcion.value} />
                                <input type="hidden" name={`poc_schema[${i}][options][${j}][label]`} value={opcion.label} />
                                <Input placeholder="valor" bind:value={opcion.value} class="flex-1" />
                                <Input placeholder="etiqueta" bind:value={opcion.label} class="flex-1" />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar opción"
                                    onclick={() => eliminarOpcion(i, j)}
                                    class="h-8 w-8 shrink-0"
                                >
                                    <Trash2 class="h-3.5 w-3.5 text-destructive" />
                                </Button>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        {/each}
    {/if}
</div>
