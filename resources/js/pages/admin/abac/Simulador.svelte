<script module lang="ts">
    export const layout = {
        title: 'Simulador de Políticas ABAC',
        description: 'Auditor y evaluador de políticas de control de acceso basado en atributos (NIST SP 800-162).',
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import Play from '@lucide/svelte/icons/play';
    import Check from '@lucide/svelte/icons/check';
    import X from '@lucide/svelte/icons/x';
    import Cpu from '@lucide/svelte/icons/cpu';
    import Code2 from '@lucide/svelte/icons/code-2';
    import Lightbulb from '@lucide/svelte/icons/lightbulb';
    import Sparkles from '@lucide/svelte/icons/sparkles';
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';

    type TipoRecurso = 'reporte' | 'programa' | 'apelacion' | 'ninguno';
    type Condicion = { grupo: string; texto: string; actual: string; cumple: boolean };
    type ReglaExplicada = { regla: string; descripcion: string; decision: string; coincide: boolean; condiciones: Condicion[] };
    type Caso = { titulo: string; usuario_id: number; accion: string; tipo_recurso: TipoRecurso; recurso_id: number | null; permitido: boolean };

    let {
        usuarios,
        acciones,
        recursos,
        casos = [],
        totalReglas,
        denyByDefault,
    }: {
        usuarios: Array<{ id: number; name: string; email: string; roles: string[]; reputation_score: number }>;
        acciones: Record<string, { label: string; objeto: TipoRecurso }>;
        recursos: {
            reportes: Array<{ id: number; etiqueta: string; estado: string }>;
            programas: Array<{ id: number; etiqueta: string; estado: string; es_publico: boolean }>;
            apelaciones: Array<{ id: number; etiqueta: string; estado: string }>;
        };
        casos?: Caso[];
        totalReglas: number;
        denyByDefault: boolean;
    } = $props();

    const NOMBRE_TIPO: Record<TipoRecurso, string> = {
        reporte: 'un informe',
        programa: 'un programa',
        apelacion: 'una apelación',
        ninguno: 'ningún objeto',
    };

    const PASOS: Array<[string, string]> = [
        ['denegacion', '¿Alguna regla que deniega se cumple?'],
        ['permiso', '¿Alguna regla que permite se cumple?'],
        ['por_defecto', 'Ninguna: se deniega por defecto'],
    ];

    let usuarioSeleccionado = $state<string>('');
    let accionSeleccionada = $state<string>('reportes.crear');
    let tipoRecurso = $state<TipoRecurso>('programa');
    let recursoId = $state<string>('');

    let cargando = $state(false);
    let error = $state('');
    let resultado = $state<{
        permitido: boolean;
        decision: string;
        regla_decisiva: string | null;
        contexto: { sujeto: Record<string, unknown>; objeto: Record<string, unknown>; entorno: Record<string, unknown> };
        explicacion: {
            resumen: string;
            paso: 'denegacion' | 'permiso' | 'por_defecto';
            reglas: ReglaExplicada[];
            casi: { regla: string; descripcion: string; faltan: Condicion[] } | null;
        };
    } | null>(null);

    let pestanaResultado = $state<'reglas' | 'atributos'>('reglas');
    let verTodas = $state(false);

    const tipoNecesario = $derived(acciones[accionSeleccionada]?.objeto ?? 'ninguno');
    const reglasVisibles = $derived(
        resultado ? (verTodas ? resultado.explicacion.reglas : resultado.explicacion.reglas.filter((r) => r.coincide)) : [],
    );

    function opcionesDe(tipo: TipoRecurso): Array<{ id: number; etiqueta: string }> {
        if (tipo === 'reporte') return recursos.reportes;
        if (tipo === 'programa') return recursos.programas;
        if (tipo === 'apelacion') return recursos.apelaciones;
        return [];
    }

    function primeroDe(tipo: TipoRecurso): string {
        return String(opcionesDe(tipo)[0]?.id ?? '');
    }

    // Cada acción se evalúa sobre un tipo de objeto: al elegirla se ajusta sola.
    function alCambiarAccion() {
        tipoRecurso = tipoNecesario;
        recursoId = primeroDe(tipoRecurso);
        resultado = null;
    }

    function alCambiarTipo() {
        recursoId = primeroDe(tipoRecurso);
    }

    $effect.pre(() => {
        if (recursoId === '' && tipoRecurso !== 'ninguno') recursoId = primeroDe(tipoRecurso);
    });

    function cargarCaso(caso: Caso) {
        usuarioSeleccionado = String(caso.usuario_id);
        accionSeleccionada = caso.accion;
        tipoRecurso = caso.tipo_recurso;
        recursoId = caso.recurso_id === null ? '' : String(caso.recurso_id);
        void ejecutarSimulacion();
    }

    function tokenXsrf(): string {
        const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
        return decodeURIComponent(cookie?.slice('XSRF-TOKEN='.length) ?? '');
    }

    async function ejecutarSimulacion() {
        cargando = true;
        error = '';
        try {
            const res = await fetch('/admin/abac/simular', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Laravel deja el token en la cookie XSRF-TOKEN (no hay <meta name="csrf-token">).
                    'X-XSRF-TOKEN': tokenXsrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    usuario_id: usuarioSeleccionado ? Number(usuarioSeleccionado) : null,
                    accion: accionSeleccionada,
                    tipo_recurso: tipoRecurso,
                    recurso_id: tipoRecurso !== 'ninguno' && recursoId ? Number(recursoId) : null,
                }),
            });

            if (res.ok) {
                resultado = await res.json();
                verTodas = false;
                pestanaResultado = 'reglas';
            } else {
                error = `No se pudo simular (HTTP ${res.status}). Recarga la página e inténtalo de nuevo.`;
            }
        } catch {
            error = 'Error de conexión al simular.';
        } finally {
            cargando = false;
        }
    }

    function gruposDe(condiciones: Condicion[]): Array<[string, Condicion[]]> {
        const grupos = new Map<string, Condicion[]>();
        for (const c of condiciones) grupos.set(c.grupo, [...(grupos.get(c.grupo) ?? []), c]);
        return [...grupos.entries()];
    }
</script>

<AppHead title="Simulador ABAC" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
        <PageHeader
            title="Simulador de Políticas ABAC"
            description="Pregúntale al motor de permisos: ¿puede este usuario hacer esta acción sobre este objeto? Y por qué."
        />
        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="outline" class="border-primary/30 font-mono text-xs text-primary">{totalReglas} reglas activas</Badge>
            <Badge variant="outline" class="border-chart-2/40 font-mono text-xs text-chart-2">
                {denyByDefault ? 'Denegar por defecto' : 'Permisivo'}
            </Badge>
        </div>
    </div>

    {#if casos.length > 0}
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Sparkles class="size-4 text-primary" />
                    Casos de ejemplo
                </CardTitle>
                <CardDescription>Un clic carga el caso con datos reales y lo evalúa. El icono indica lo que responde el motor.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2" data-test="casos-ejemplo">
                {#each casos as caso (caso.titulo)}
                    <Button variant="outline" size="sm" class="h-auto py-1.5 text-left whitespace-normal" onclick={() => cargarCaso(caso)}>
                        {#if caso.permitido}
                            <Check class="mr-1.5 size-3.5 shrink-0 text-primary" />
                        {:else}
                            <X class="mr-1.5 size-3.5 shrink-0 text-destructive" />
                        {/if}
                        {caso.titulo}
                    </Button>
                {/each}
                </div>
            </CardContent>
        </Card>
    {/if}

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-5 lg:col-span-5">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Cpu class="size-4 text-primary" />
                        La pregunta
                    </CardTitle>
                    <CardDescription>¿Puede <strong>este usuario</strong> hacer <strong>esta acción</strong> sobre <strong>este objeto</strong>?</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-1.5">
                        <label for="abac-usuario" class="text-xs font-semibold text-foreground">1. Usuario (quién)</label>
                        <select id="abac-usuario" bind:value={usuarioSeleccionado} class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            <option value="">Visitante sin sesión</option>
                            {#each usuarios as u (u.id)}
                                <option value={String(u.id)}>{u.name} [{u.roles.join(', ')}] ({u.reputation_score} pts)</option>
                            {/each}
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="abac-accion" class="text-xs font-semibold text-foreground">2. Acción (qué quiere hacer)</label>
                        <select id="abac-accion" bind:value={accionSeleccionada} onchange={alCambiarAccion} class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            {#each Object.entries(acciones) as [slug, accion] (slug)}
                                <option value={slug}>{accion.label} ({slug})</option>
                            {/each}
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="abac-tipo-recurso" class="text-xs font-semibold text-foreground">3. Objeto (sobre qué)</label>
                        <select id="abac-tipo-recurso" bind:value={tipoRecurso} onchange={alCambiarTipo} class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            <option value="reporte">Un informe</option>
                            <option value="programa">Un programa</option>
                            <option value="apelacion">Una apelación</option>
                            <option value="ninguno">Ninguno (acción general)</option>
                        </select>
                        {#if tipoRecurso !== tipoNecesario}
                            <p class="flex items-start gap-1.5 text-xs text-chart-4" data-test="aviso-tipo-objeto">
                                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                Esta acción se comprueba sobre {NOMBRE_TIPO[tipoNecesario]}. Con {NOMBRE_TIPO[tipoRecurso]}, el motor no puede verificar, por ejemplo, a quién pertenece, y denegará.
                            </p>
                        {/if}
                    </div>

                    {#if tipoRecurso !== 'ninguno'}
                        <select id="abac-recurso-id" aria-label="Objeto concreto" bind:value={recursoId} class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs focus:border-primary focus:outline-none">
                            {#each opcionesDe(tipoRecurso) as o (o.id)}
                                <option value={String(o.id)}>{o.etiqueta}</option>
                            {/each}
                        </select>
                    {/if}

                    <Button class="mt-2 w-full font-semibold" onclick={ejecutarSimulacion} disabled={cargando}>
                        <Play class="mr-2 size-4" />
                        {cargando ? 'Evaluando…' : 'Ejecutar Simulación ABAC'}
                    </Button>
                    {#if error}
                        <p role="alert" class="text-sm text-destructive">{error}</p>
                    {/if}
                </CardContent>
            </Card>

            <Card class="bg-muted/30">
                <CardContent class="space-y-2 pt-6 text-xs text-muted-foreground">
                    <p class="flex items-center gap-1.5 font-semibold text-foreground">
                        <Lightbulb class="size-3.5 text-primary" />
                        Cómo decide el motor
                    </p>
                    <ol class="list-decimal space-y-1 pl-4">
                        <li>Busca las reglas que hablan de esa acción.</li>
                        <li>Si alguna regla que <strong>deniega</strong> se cumple, deniega. Siempre gana.</li>
                        <li>Si no, y alguna regla que <strong>permite</strong> se cumple, permite.</li>
                        <li>Si ninguna se cumple, <strong>deniega por defecto</strong>: lo que no está permitido explícitamente, está prohibido.</li>
                    </ol>
                    <p>
                        A diferencia de un sistema por roles (RBAC), cada regla mira atributos del <strong>usuario</strong> (rol, reputación,
                        suspensión, empresa), del <strong>objeto</strong> (dueño, estado, a quién está asignado) y del <strong>contexto</strong> (NIST SP 800-162).
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="space-y-5 lg:col-span-7">
            {#if resultado}
                {@const exp = resultado.explicacion}
                <div class="space-y-3 rounded-xl border-2 p-5 shadow-lg {resultado.permitido ? 'border-primary bg-primary/10' : 'border-destructive bg-destructive/10'}" data-test="veredicto">
                    <div class="flex items-center gap-2">
                        {#if resultado.permitido}
                            <ShieldCheck class="size-7 text-primary" />
                            <span class="text-xl font-extrabold tracking-tight text-foreground">ACCESO PERMITIDO</span>
                        {:else}
                            <ShieldAlert class="size-7 text-destructive" />
                            <span class="text-xl font-extrabold tracking-tight text-foreground">ACCESO DENEGADO</span>
                        {/if}
                    </div>
                    <p class="text-sm font-medium text-foreground" data-test="resumen">{exp.resumen}</p>

                    <ol class="grid gap-2 text-xs sm:grid-cols-3" data-test="pasos">
                        {#each PASOS as [paso, texto], i (paso)}
                            <li class="rounded-md border px-2 py-1.5 {exp.paso === paso ? 'border-foreground/50 bg-background font-semibold text-foreground' : 'border-border/60 text-muted-foreground'}">
                                <span class="font-mono">{i + 1}.</span> {texto}
                                {#if exp.paso === paso}<span class="block text-[11px] font-normal">← aquí se decidió</span>{/if}
                            </li>
                        {/each}
                    </ol>

                    {#if exp.casi && exp.casi.faltan.length > 0}
                        <div class="rounded-md border border-border bg-background/70 p-3 text-xs" data-test="casi">
                            <p class="font-semibold text-foreground">Para que se permitiera, faltó:</p>
                            <p class="mt-0.5 text-muted-foreground">La regla que más se acercó: «{exp.casi.descripcion}»</p>
                            <ul class="mt-1.5 space-y-0.5">
                                {#each exp.casi.faltan as c, i (i)}
                                    <li class="flex gap-1.5">
                                        <X class="mt-0.5 size-3 shrink-0 text-destructive" />
                                        <span><strong>{c.grupo}:</strong> {c.texto} <span class="text-muted-foreground">(ahora: {c.actual})</span></span>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/if}
                </div>

                <div class="flex border-b border-border text-sm">
                    <button type="button" class="border-b-2 px-4 py-2 font-medium transition-colors {pestanaResultado === 'reglas' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}" onclick={() => (pestanaResultado = 'reglas')}>
                        Reglas ({exp.reglas.length} evaluadas)
                    </button>
                    <button type="button" class="border-b-2 px-4 py-2 font-medium transition-colors {pestanaResultado === 'atributos' ? 'border-primary font-semibold text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}" onclick={() => (pestanaResultado = 'atributos')}>
                        Datos técnicos
                    </button>
                </div>

                {#if pestanaResultado === 'reglas'}
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{verTodas ? 'Todas las reglas que hablan de esta acción.' : 'Solo las reglas que se cumplieron.'}</span>
                        <Button variant="ghost" size="sm" class="h-7 text-xs" onclick={() => (verTodas = !verTodas)}>
                            {verTodas ? 'Ver solo las que se cumplieron' : `Ver las ${exp.reglas.length} evaluadas`}
                        </Button>
                    </div>
                    <div class="space-y-3" data-test="reglas">
                        {#each reglasVisibles as item (item.regla)}
                            <div class="space-y-2 rounded-lg border p-3.5 {item.coincide ? (item.decision === 'permitir' ? 'border-primary/50 bg-primary/5' : 'border-destructive/50 bg-destructive/5') : 'border-border/60 bg-muted/20'}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="min-w-0 flex-1 text-sm font-medium text-foreground">{item.descripcion}</p>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <Badge variant={item.decision === 'permitir' ? 'secondary' : 'destructive'} class="text-[10px]">
                                            {item.decision === 'permitir' ? 'permite' : 'deniega'}
                                        </Badge>
                                        <Badge variant="outline" class="text-[10px]">{item.coincide ? 'se cumple' : 'no se cumple'}</Badge>
                                    </div>
                                </div>
                                <p class="font-mono text-[11px] text-muted-foreground">{item.regla}{item.regla === resultado.regla_decisiva ? ' · decidió el resultado' : ''}</p>
                                {#if item.condiciones.length > 0}
                                    <div class="space-y-1.5 border-t border-border/60 pt-2 text-xs">
                                        {#each gruposDe(item.condiciones) as [grupo, condiciones] (grupo)}
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{grupo}</p>
                                                <ul class="space-y-0.5">
                                                    {#each condiciones as c, i (i)}
                                                        <li class="flex gap-1.5">
                                                            {#if c.cumple}
                                                                <Check class="mt-0.5 size-3 shrink-0 text-primary" />
                                                            {:else}
                                                                <X class="mt-0.5 size-3 shrink-0 text-destructive" />
                                                            {/if}
                                                            <span>{c.texto} <span class="text-muted-foreground">(ahora: {c.actual})</span></span>
                                                        </li>
                                                    {/each}
                                                </ul>
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="text-xs text-muted-foreground">Sin condiciones: aplica a cualquiera que intente esta acción.</p>
                                {/if}
                            </div>
                        {:else}
                            <p class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                Ninguna regla se cumplió. Pulsa «Ver las {exp.reglas.length} evaluadas» para ver qué condición falló en cada una.
                            </p>
                        {/each}
                    </div>
                {:else}
                    <div class="space-y-4">
                        {#each [['Usuario ($sujeto)', resultado.contexto.sujeto], ['Objeto ($objeto)', resultado.contexto.objeto], ['Contexto ($entorno)', resultado.contexto.entorno]] as const as [titulo, datos] (titulo)}
                            <div class="space-y-2 rounded-lg border border-border bg-card p-4">
                                <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary">
                                    <Code2 class="size-3.5" />
                                    {titulo}
                                </span>
                                <pre class="overflow-x-auto rounded bg-muted/40 p-3 font-mono text-[11px] text-foreground">{JSON.stringify(datos, null, 2)}</pre>
                            </div>
                        {/each}
                    </div>
                {/if}
            {:else}
                <div class="flex flex-col items-center justify-center space-y-3 rounded-xl border-2 border-dashed border-border px-6 py-16 text-center">
                    <div class="rounded-full bg-primary/10 p-4 text-primary">
                        <Cpu class="size-10" />
                    </div>
                    <h3 class="text-lg font-bold">Haz una pregunta al motor</h3>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Elige un caso de ejemplo de arriba, o arma la pregunta a la izquierda y pulsa «Ejecutar Simulación ABAC».
                        Verás el veredicto, qué regla lo decidió y qué condición se cumplió o falló.
                    </p>
                </div>
            {/if}
        </div>
    </div>
</div>
