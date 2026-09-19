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
    import { Form } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
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
    } from '@/components/ui/select';
    import { store as programaStore } from '@/routes/programas';

    let objetivos = $state<{ tipo: string; valor: string; descripcion: string }[]>([]);

    function agregarObjetivo() {
        objetivos = [...objetivos, { tipo: 'web', valor: '', descripcion: '' }];
    }

    function eliminarObjetivo(index: number) {
        objetivos = objetivos.filter((_, i) => i !== index);
    }
</script>

<AppHead title="Crear Programa" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Crear Programa"
        description="Define un nuevo programa de divulgacion de vulnerabilidades"
    />

    <Form method="post" action={programaStore()} class="space-y-6">
        {#snippet children({ errors, processing })}
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
                        <Label for="descripcion">Descripcion *</Label>
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

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="recompensa_min">Recompensa minima *</Label>
                            <Input
                                id="recompensa_min"
                                name="recompensa_min"
                                type="number"
                                min="0"
                                placeholder="100"
                                required
                            />
                            <InputError message={errors.recompensa_min} />
                        </div>

                        <div class="space-y-2">
                            <Label for="recompensa_max">Recompensa maxima *</Label>
                            <Input
                                id="recompensa_max"
                                name="recompensa_max"
                                type="number"
                                min="0"
                                placeholder="5000"
                                required
                            />
                            <InputError message={errors.recompensa_max} />
                        </div>

                        <div class="space-y-2">
                            <Label for="moneda">Moneda</Label>
                            <Input
                                id="moneda"
                                name="moneda"
                                value="USD"
                                placeholder="USD"
                            />
                            <InputError message={errors.moneda} />
                        </div>
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
                            <span>Publico</span>
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Objetivos</h3>
                            <p class="text-xs text-muted-foreground">Define los objetivos del programa</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" onclick={agregarObjetivo}>
                            <Plus class="mr-1 h-4 w-4" />
                            Agregar
                        </Button>
                    </div>

                    {#if objetivos.length === 0}
                        <p class="text-xs text-muted-foreground">No hay objetivos definidos. Haz clic en "Agregar" para crear uno.</p>
                    {:else}
                        {#each objetivos as _, i (i)}
                            <div class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[140px_1fr_1fr_auto]">
                                <input type="hidden" name={`objetivos[${i}][tipo]`} value={objetivos[i].tipo} />
                                <input type="hidden" name={`objetivos[${i}][valor]`} value={objetivos[i].valor} />
                                <input type="hidden" name={`objetivos[${i}][descripcion]`} value={objetivos[i].descripcion} />

                                <Select
                                    value={objetivos[i].tipo}
                                    onValueChange={(v) => { objetivos[i].tipo = v; }}
                                >
                                    <SelectTrigger>
                                        <span>Tipo</span>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="web">Web</SelectItem>
                                        <SelectItem value="api">API</SelectItem>
                                        <SelectItem value="movil">Movil</SelectItem>
                                        <SelectItem value="otro">Otro</SelectItem>
                                    </SelectContent>
                                </Select>

                                <Input
                                    placeholder="Valor (ej: *.ejemplo.com)"
                                    bind:value={objetivos[i].valor}
                                />

                                <Input
                                    placeholder="Descripcion (opcional)"
                                    bind:value={objetivos[i].descripcion}
                                />

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onclick={() => eliminarObjetivo(i)}
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        {/each}
                    {/if}

                    {#if errors.objetivos}
                        <InputError message={errors.objetivos} />
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
