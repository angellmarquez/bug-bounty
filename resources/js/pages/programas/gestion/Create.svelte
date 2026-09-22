<script module lang="ts">
    import { index as programasIndex, gestion as programasGestion } from '@/routes/programas';

    export const layout = {
        breadcrumbs: [
            { title: 'Programas', href: programasIndex() },
            { title: 'Crear Programa', href: programasGestion() },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Spinner } from '@/components/ui/spinner';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
        SelectValue,
    } from '@/components/ui/select';
    import type { ReputacionConfig } from '@/lib/rangos';
    import { store as programaStore } from '@/routes/programas';
    import { TIPOS_OBJETIVO } from '@/lib/tipo-objetivo';

    const esEmpresa = $derived(((page.props.userRoles as string[] | undefined) ?? []).includes('empresa'));

    // Niveles de acceso (con su rango) definidos en config/reputacion.php.
    const niveles = $derived((page.props.reputacionConfig as ReputacionConfig).niveles);

    // Se empieza con un objetivo vacío: el programa necesita al menos uno.
    let objetivos = $state<{ tipo: string; valor: string; descripcion: string }[]>([
        { tipo: 'web', valor: '', descripcion: '' },
    ]);

    function agregarObjetivo() {
        objetivos = [...objetivos, { tipo: 'web', valor: '', descripcion: '' }];
    }

    function eliminarObjetivo(index: number) {
        objetivos = objetivos.filter((_, i) => i !== index);
    }
</script>

<AppHead title="Crear Programa" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex items-center gap-4">
        <BotonVolver
            href={esEmpresa ? '/empresa' : '/gestion/programas'}
            etiqueta={esEmpresa ? 'Volver al panel de empresa' : 'Volver a programas'}
        />
        <PageHeader
            title="Crear Programa"
            description="Define un nuevo programa de divulgación de vulnerabilidades"
        />
    </div>

    <Form method="post" action={programaStore()} class="space-y-6">
        {#snippet children({ errors, processing })}
            {@const errorObjetivos = Object.entries(errors).find(([clave]) => clave === 'objetivos' || clave.startsWith('objetivos.'))?.[1]}
            <Card>
                <CardContent class="pt-6 space-y-4">
                    <div class="space-y-2">
                        <Label for="nombre">Nombre *</Label>
                        <Input
                            id="nombre"
                            name="nombre"
                            placeholder="Ej: Programa de Seguridad Web"
                            required
                        />
                        <InputError message={errors.nombre} />
                    </div>

                    <div class="space-y-2">
                        <Label for="descripcion">Descripción *</Label>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            placeholder="Describe el programa, alcance y reglas de participacion..."
                            rows="4"
                            required
                            class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <InputError message={errors.descripcion} />
                    </div>

                    <div class="space-y-2">
                        <Label for="bugs_buscados">Que bugs buscas</Label>
                        <textarea
                            id="bugs_buscados"
                            name="bugs_buscados"
                            placeholder="Ej: inyeccion SQL, XSS, fallos de autenticacion, exposicion de datos personales..."
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">
                            Los investigadores lo veran antes de enviarte un reporte.
                        </p>
                        <InputError message={errors.bugs_buscados} />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="inicia_en">Fecha de inicio</Label>
                            <Input
                                id="inicia_en"
                                name="inicia_en"
                                type="date"
                            />
                            <InputError message={errors.inicia_en} />
                        </div>

                        <div class="space-y-2">
                            <Label for="termina_en">Fecha de fin</Label>
                            <Input
                                id="termina_en"
                                name="termina_en"
                                type="date"
                            />
                            <InputError message={errors.termina_en} />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <Label class="flex items-center space-x-3">
                            <Checkbox name="requiere_poc" value="1" />
                            <span>Requiere PoC</span>
                        </Label>

                        <Label class="flex items-center space-x-3">
                            <Checkbox name="es_publico" value="1" checked={true} />
                            <span>Público</span>
                        </Label>
                    </div>

                    <div class="max-w-sm space-y-2">
                        <Label for="nivel_acceso">Nivel de acceso</Label>
                        <select
                            id="nivel_acceso"
                            name="nivel_acceso"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            value={"bajo"}
                        >
                            {#each niveles as nivel (nivel.valor)}
                                <option value={nivel.valor}>
                                    {nivel.etiqueta} · rango {nivel.rangoNombre} o superior ({nivel.minimo}+ pts)
                                </option>
                            {/each}
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Solo los investigadores con ese rango de reputación (o más) verán el programa y podrán enviar reportes.
                        </p>
                        <InputError message={errors.nivel_acceso} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Objetivos *</h3>
                            <p class="text-xs text-muted-foreground">Obligatorio: indica al menos un sistema que los investigadores puedan investigar (un dominio, una API, una app).</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" onclick={agregarObjetivo}>
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar
                        </Button>
                    </div>

                    {#if objetivos.length === 0}
                        <p class="text-xs text-muted-foreground">Aún no hay objetivos. Sin al menos uno no se puede crear ni publicar el programa: haz clic en "Agregar".</p>
                    {:else}
                        {#each objetivos as _, i (i)}
                            <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[140px_1fr_1fr_auto]">
                                <input type="hidden" name={`objetivos[${i}][tipo]`} value={objetivos[i].tipo} />
                                <input type="hidden" name={`objetivos[${i}][valor]`} value={objetivos[i].valor} />
                                <input type="hidden" name={`objetivos[${i}][descripcion]`} value={objetivos[i].descripcion} />

                                <Select
                                    value={objetivos[i].tipo}
                                    onValueChange={(v) => { objetivos[i].tipo = v; }}
                                    items={TIPOS_OBJETIVO}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {#each TIPOS_OBJETIVO as tipo (tipo.value)}
                                            <SelectItem value={tipo.value} label={tipo.label}>
                                                {tipo.label}
                                            </SelectItem>
                                        {/each}
                                    </SelectContent>
                                </Select>

                                <Input
                                    placeholder="Valor (ej: *.ejemplo.com)"
                                    bind:value={objetivos[i].valor}
                                />

                                <Input
                                    placeholder="Descripción (opcional)"
                                    bind:value={objetivos[i].descripcion}
                                />

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar objetivo"
                                    onclick={() => eliminarObjetivo(i)}
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        {/each}
                    {/if}

                    {#if errorObjetivos}
                        <InputError message={errorObjetivos} />
                    {/if}
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {#if processing}<Spinner />{/if}
                    Crear Programa
                </Button>
            </div>
        {/snippet}
    </Form>
</div>
