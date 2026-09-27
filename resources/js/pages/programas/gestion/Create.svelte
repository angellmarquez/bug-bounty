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
    import Globe from '@lucide/svelte/icons/globe';
    import Lock from '@lucide/svelte/icons/lock';
    import AppHead from '@/components/AppHead.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import InputError from '@/components/InputError.svelte';
    import PocSchemaEditor from '@/components/PocSchemaEditor.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
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
    import Coins from '@lucide/svelte/icons/coins';
    import Sparkles from '@lucide/svelte/icons/sparkles';

    interface Props {
        empresaPlan?: {
            plan: string;
            es_profesional: boolean;
            expira_en: string | null;
        };
    }

    let { empresaPlan }: Props = $props();

    const esEmpresa = $derived(((page.props.userRoles as string[] | undefined) ?? []).includes('empresa'));
    const esProfesional = $derived(empresaPlan?.es_profesional ?? false);

    // Niveles de acceso (con su rango) definidos en config/reputacion.php.
    const niveles = $derived((page.props.reputacionConfig as ReputacionConfig).niveles);

    let esPublico = $state(true);
    let tieneRecompensas = $state(false);
    let recompensaMin = $state('50');
    let recompensaMax = $state('2000');
    let tablaBounties = $state({
        critica: '1500',
        alta: '750',
        media: '300',
        baja: '100',
    });

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
                <CardContent class="space-y-4">
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
                        <Label for="bugs_buscados">Qué bugs buscas</Label>
                        <textarea
                            id="bugs_buscados"
                            name="bugs_buscados"
                            placeholder="Ej: inyección SQL, XSS, fallos de autenticación, exposición de datos personales..."
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">
                            Los investigadores lo verán antes de enviarte un reporte.
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

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label class="text-base font-medium">Visibilidad del programa</Label>
                            {#if !esProfesional}
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-500 border border-amber-500/20">
                                    <Sparkles class="h-3 w-3" /> Plan Comunitario
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">
                                    <Sparkles class="h-3 w-3" /> Plan Profesional Activo
                                </span>
                            {/if}
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50 {esPublico ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border'}"
                            >
                                <input
                                    type="radio"
                                    name="visibilidad_radio"
                                    class="sr-only"
                                    checked={esPublico}
                                    onchange={() => (esPublico = true)}
                                />
                                <Globe class="mt-0.5 h-5 w-5 shrink-0 {esPublico ? 'text-primary' : 'text-muted-foreground'}" />
                                <div class="space-y-1">
                                    <p class="text-sm font-medium {esPublico ? 'text-foreground' : 'text-muted-foreground'}">
                                        Público (Directorio abierto)
                                    </p>
                                    <p class="text-xs leading-relaxed text-muted-foreground">
                                        Visible en el listado para todos los investigadores que cumplan el nivel de reputación requerido.
                                    </p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-lg border p-4 transition-colors {esProfesional ? 'cursor-pointer hover:bg-muted/50' : 'opacity-60 cursor-not-allowed bg-muted/20'} {!esPublico ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border'}"
                            >
                                <input
                                    type="radio"
                                    name="visibilidad_radio"
                                    class="sr-only"
                                    disabled={!esProfesional}
                                    checked={!esPublico}
                                    onchange={() => { if (esProfesional) esPublico = false; }}
                                />
                                <Lock class="mt-0.5 h-5 w-5 shrink-0 {!esPublico ? 'text-primary' : 'text-muted-foreground'}" />
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium {!esPublico ? 'text-foreground' : 'text-muted-foreground'}">
                                            Privado (Por invitación exclusiva)
                                        </p>
                                        {#if !esProfesional}
                                            <span class="text-[10px] uppercase font-bold bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded">Pro</span>
                                        {/if}
                                    </div>
                                    <p class="text-xs leading-relaxed text-muted-foreground">
                                        {#if esProfesional}
                                            Oculto del directorio público. Tú decides qué investigadores participan invitándolos por su correo.
                                        {:else}
                                            Requiere suscripción al <strong>Plan Profesional</strong> para crear programas privados protegidos.
                                        {/if}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <input type="hidden" name="es_publico" value={esPublico ? '1' : '0'} />
                        <InputError message={errors.es_publico} />
                    </div>

                    <div class="max-w-md space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="nivel_acceso">Filtro de Investigadores por Reputación</Label>
                        </div>
                        <select
                            id="nivel_acceso"
                            name="nivel_acceso"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            value={"bajo"}
                        >
                            {#each niveles as nivel (nivel.valor)}
                                {@const requierePro = nivel.valor !== 'bajo'}
                                <option
                                    value={nivel.valor}
                                    disabled={!esProfesional && requierePro}
                                >
                                    {nivel.etiqueta} · rango {nivel.rangoNombre} ({nivel.minimo}+ pts)
                                    {!esProfesional && requierePro ? ' [Requiere Plan Pro]' : ''}
                                </option>
                            {/each}
                        </select>
                        <p class="text-xs text-muted-foreground">
                            {#if !esProfesional}
                                Con el Plan Comunitario tus programas están abiertos a todos los investigadores registrados (Nivel Bronce). Actualiza a Profesional para filtrar solo investigadores Plata u Oro.
                            {:else}
                                Solo los investigadores con ese rango de reputación (o más) verán el programa y podrán enviar reportes.
                            {/if}
                        </p>
                        <InputError message={errors.nivel_acceso} />
                    </div>
                </CardContent>
            </Card>

            <!-- Recompensas (Bounties) Cripto -->
            <Card class="border-border">
                <CardContent class="space-y-4 pt-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-primary/10 p-2 text-primary">
                                <Coins class="h-6 w-6" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                                    Recompensas Económicas (Bounties Cripto / USDC)
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Atrae a los mejores investigadores ofreciendo recompensas directas pagadas en stablecoins (USDC).
                                </p>
                            </div>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                name="tiene_recompensas"
                                value="1"
                                class="sr-only peer"
                                checked={tieneRecompensas}
                                onchange={(e) => tieneRecompensas = e.currentTarget.checked}
                            />
                            <div class="w-11 h-6 bg-muted peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    {#if tieneRecompensas}
                        <div class="mt-4 pt-4 border-t border-border/60 space-y-4">
                            <input type="hidden" name="moneda" value="USDC" />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="recompensa_min">Recompensa mínima estimada (USDC)</Label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-xs text-muted-foreground">$</span>
                                        <Input
                                            id="recompensa_min"
                                            name="recompensa_min"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="pl-7"
                                            bind:value={recompensaMin}
                                        />
                                    </div>
                                    <InputError message={errors.recompensa_min} />
                                </div>

                                <div class="space-y-2">
                                    <Label for="recompensa_max">Recompensa máxima estimada (USDC)</Label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-xs text-muted-foreground">$</span>
                                        <Input
                                            id="recompensa_max"
                                            name="recompensa_max"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="pl-7"
                                            bind:value={recompensaMax}
                                        />
                                    </div>
                                    <InputError message={errors.recompensa_max} />
                                </div>
                            </div>

                            <div class="space-y-2 pt-2">
                                <Label class="text-xs uppercase font-semibold text-muted-foreground tracking-wider">
                                    Tabla orientativa de recompensas por severidad (USDC)
                                </Label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <div class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 space-y-1">
                                        <span class="text-xs font-medium text-destructive">Crítica</span>
                                        <div class="relative mt-1">
                                            <span class="absolute left-2.5 top-2 text-xs text-muted-foreground">$</span>
                                            <Input
                                                name="tabla_recompensas[critica]"
                                                type="number"
                                                class="pl-6 h-8 text-xs font-mono"
                                                bind:value={tablaBounties.critica}
                                            />
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-chart-4/30 bg-chart-4/5 p-3 space-y-1">
                                        <span class="text-xs font-medium text-chart-4">Alta</span>
                                        <div class="relative mt-1">
                                            <span class="absolute left-2.5 top-2 text-xs text-muted-foreground">$</span>
                                            <Input
                                                name="tabla_recompensas[alta]"
                                                type="number"
                                                class="pl-6 h-8 text-xs font-mono"
                                                bind:value={tablaBounties.alta}
                                            />
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-chart-2/30 bg-chart-2/5 p-3 space-y-1">
                                        <span class="text-xs font-medium text-chart-2">Media</span>
                                        <div class="relative mt-1">
                                            <span class="absolute left-2.5 top-2 text-xs text-muted-foreground">$</span>
                                            <Input
                                                name="tabla_recompensas[media]"
                                                type="number"
                                                class="pl-6 h-8 text-xs font-mono"
                                                bind:value={tablaBounties.media}
                                            />
                                        </div>
                                    </div>

                                    <div class="rounded-lg border border-chart-1/30 bg-chart-1/5 p-3 space-y-1">
                                        <span class="text-xs font-medium text-chart-1">Baja</span>
                                        <div class="relative mt-1">
                                            <span class="absolute left-2.5 top-2 text-xs text-muted-foreground">$</span>
                                            <Input
                                                name="tabla_recompensas[baja]"
                                                type="number"
                                                class="pl-6 h-8 text-xs font-mono"
                                                bind:value={tablaBounties.baja}
                                            />
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-muted-foreground">
                                    Los pagos no son custodiados por la plataforma. Al validar un reporte transferirás directamente a la billetera EVM del investigador y registrarás el hash de transacción.
                                </p>
                            </div>
                        </div>
                    {/if}
                </CardContent>
            </Card>

            <Card>
                <CardContent class="space-y-4">
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

            <Card>
                <CardContent>
                    <PocSchemaEditor {errors} />
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
