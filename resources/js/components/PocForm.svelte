<script lang="ts">
    import Plus from '@lucide/svelte/icons/plus';
    import X from '@lucide/svelte/icons/x';
    import Info from '@lucide/svelte/icons/info';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Button } from '@/components/ui/button';
    import InputError from '@/components/InputError.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
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
    import type { PocSchemaField } from '@/types/domain';
    import { validarPoc } from '@/lib/poc-schema';

    let {
        schema = [],
        data = $bindable({}),
        errors = $bindable({}),
    }: {
        schema: PocSchemaField[];
        data: Record<string, unknown>;
        errors: Record<string, string>;
    } = $props();

    let previewVisible = $state(false);

    function actualizarCampo(name: string, value: unknown) {
        data = { ...data, [name]: value };
        const validacion = validarPoc(data, schema);
        const newErrors: Record<string, string> = {};
        for (const [key, msg] of Object.entries(validacion)) {
            if (!key.includes('.') || key.startsWith(name)) {
                newErrors[key] = msg;
            }
        }
        for (const key of Object.keys(errors)) {
            if (key === name || key.startsWith(name + '.')) {
                continue;
            }
            newErrors[key] = errors[key];
        }
        errors = newErrors;
    }

    function agregarInstancia(name: string) {
        const current = Array.isArray(data[name]) ? [...(data[name] as string[])] : [];
        current.push('');
        data = { ...data, [name]: current };
    }

    function removerInstancia(name: string, index: number) {
        const current = Array.isArray(data[name]) ? [...(data[name] as string[])] : [];
        current.splice(index, 1);
        data = { ...data, [name]: current };
        const newErrors: Record<string, string> = {};
        for (const [key, msg] of Object.entries(errors)) {
            if (!key.startsWith(name + '.')) {
                newErrors[key] = msg;
            }
        }
        errors = newErrors;
    }

    function actualizarInstancia(name: string, index: number, value: string) {
        const current = Array.isArray(data[name]) ? [...(data[name] as string[])] : [];
        current[index] = value;
        data = { ...data, [name]: current };
        const fieldErrors: Record<string, string> = {};
        for (const [key, msg] of Object.entries(errors)) {
            if (key !== `${name}.${index}`) {
                fieldErrors[key] = msg;
            }
        }
        if (!value || value.trim() === '') {
            const field = schema.find((f) => f.name === name);
            if (field?.required) {
                fieldErrors[`${name}.${index}`] = `${field.label} #${index + 1} no puede estar vacio`;
            }
        }
        errors = fieldErrors;
    }
</script>

{#if schema.length === 0}
    <EmptyState
        title="Sin campos PoC"
        description="Este programa no requiere una prueba de concepto estructurada. Puedes omitir este paso."
    />
{:else}
    <div class="flex gap-6">
        <div class="flex-1 space-y-6">
            {#each schema as field (field.name)}
                <Card>
                    <CardContent class="pt-6">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <Label for={field.name} class="text-sm font-medium">
                                    {field.label}
                                    {#if field.required}
                                        <span class="text-destructive">*</span>
                                    {/if}
                                </Label>
                                {#if field.help}
                                    <div class="group relative">
                                        <Info class="h-3 w-3 text-muted-foreground" />
                                        <div class="absolute bottom-full left-1/2 z-50 mb-2 hidden -translate-x-1/2 rounded-md border border-border bg-popover p-2 text-xs text-popover-foreground shadow-md group-hover:block">
                                            {field.help}
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            {#if field.repeatable}
                                <div class="space-y-2">
                                    {#if Array.isArray(data[field.name])}
                                        {#each (data[field.name] as string[]) as _, i (i)}
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-muted-foreground w-6">{i + 1}.</span>
                                                <Input
                                                    value={(data[field.name] as string[])[i] ?? ''}
                                                    oninput={(e) => actualizarInstancia(field.name, i, e.currentTarget.value)}
                                                    placeholder={field.placeholder}
                                                    class="flex-1"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onclick={() => removerInstancia(field.name, i)}
                                                    disabled={(data[field.name] as string[]).length <= 1}
                                                    class="h-8 w-8 shrink-0"
                                                >
                                                    <X class="h-4 w-4" />
                                                </Button>
                                            </div>
                                            {#if errors[`${field.name}.${i}`]}
                                                <InputError message={errors[`${field.name}.${i}`]} />
                                            {/if}
                                        {/each}
                                    {/if}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={() => agregarInstancia(field.name)}
                                        class="mt-2"
                                    >
                                        <Plus class="mr-1 h-3 w-3" />
                                        Agregar
                                    </Button>
                                </div>
                            {:else if field.type === 'textarea' || field.type === 'code'}
                                <textarea
                                    id={field.name}
                                    value={(data[field.name] as string) ?? ''}
                                    oninput={(e) => actualizarCampo(field.name, e.currentTarget.value)}
                                    placeholder={field.placeholder}
                                    rows={field.type === 'code' ? 8 : 4}
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {field.type === 'code' ? 'font-mono' : ''}"
                                ></textarea>
                            {:else if field.type === 'select' && field.options}
                                <Select
                                    value={(data[field.name] as string) ?? ''}
                                    onValueChange={(v) => actualizarCampo(field.name, v)}
                                    items={field.options}
                                >
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder={field.placeholder ?? 'Seleccionar...'} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each field.options as option (option.value)}
                                            <SelectItem value={option.value} label={option.label}>
                                                {option.label}
                                            </SelectItem>
                                        {/each}
                                    </SelectContent>
                                </Select>
                            {:else}
                                <Input
                                    id={field.name}
                                    type={field.type === 'number' ? 'number' : field.type === 'url' ? 'url' : 'text'}
                                    value={(data[field.name] as string) ?? ''}
                                    oninput={(e) => actualizarCampo(field.name, e.currentTarget.value)}
                                    placeholder={field.placeholder}
                                />
                            {/if}

                            {#if errors[field.name]}
                                <InputError message={errors[field.name]} />
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>

        <div class="hidden w-80 lg:block">
            <div class="sticky top-4">
                <Card class="max-h-[calc(100vh-10rem)] overflow-auto">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm">Preview</CardTitle>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onclick={() => (previewVisible = !previewVisible)}
                                class="h-7 text-xs"
                            >
                                {previewVisible ? 'Ocultar' : 'Ver'}
                            </Button>
                        </div>
                    </CardHeader>
                    {#if previewVisible}
                        <CardContent>
                            <pre class="whitespace-pre-wrap rounded-lg bg-muted p-3 text-xs font-mono">{@render renderPreview()}</pre>
                        </CardContent>
                    {/if}
                </Card>
            </div>
        </div>
    </div>
{/if}

{#snippet renderPreview()}
    ## Prueba de Concepto

    {#each schema as field}
        ### {field.label}

        {#if field.repeatable && Array.isArray(data[field.name])}
            {#each (data[field.name] as string[]) as item, i}
                {i + 1}. {item || '_vacio_'}
            {/each}
        {:else if field.type === 'code' || field.type === 'textarea'}
```
{((data[field.name] as string) ?? '') || '_sin contenido_'}
```
        {:else}
            {(data[field.name] as string) ?? '_N/A_'}
        {/if}

    {/each}
{/snippet}
