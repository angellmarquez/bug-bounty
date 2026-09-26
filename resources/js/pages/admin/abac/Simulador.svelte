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
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import Cpu from '@lucide/svelte/icons/cpu';
    import Layers from '@lucide/svelte/icons/layers';
    import Code2 from '@lucide/svelte/icons/code-2';

    let {
        usuarios,
        acciones,
        recursos,
        totalReglas,
        denyByDefault,
    }: {
        usuarios: Array<{
            id: number;
            name: string;
            email: string;
            roles: string[];
            reputation_score: number;
        }>;
        acciones: Record<string, string>;
        recursos: {
            reportes: Array<{ id: number; etiqueta: string; estado: string }>;
            programas: Array<{ id: number; etiqueta: string; estado: string; es_publico: boolean }>;
            apelaciones: Array<{ id: number; etiqueta: string; estado: string }>;
        };
        totalReglas: number;
        denyByDefault: boolean;
    } = $props();

    let usuarioSeleccionado = $state<string>('');
    let accionSeleccionada = $state<string>('reportes.ver');
    let tipoRecurso = $state<'reporte' | 'programa' | 'apelacion' | 'ninguno'>('reporte');
    let recursoId = $state<string>('');

    let cargando = $state(false);
    let resultado = $state<{
        permitido: boolean;
        decision: string;
        motivo: string;
        regla_decisiva: string | null;
        contexto: {
            sujeto: Record<string, any>;
            objeto: Record<string, any>;
            entorno: Record<string, any>;
        };
        detalle: Array<{
            regla: string;
            decision: string;
            grupos: Record<string, boolean>;
            coincide: boolean;
        }>;
    } | null>(null);

    let pestanaResultado = $state<'arbol' | 'atributos'>('arbol');

    // Inicializar primer recurso si hay
    $effect(() => {
        if (tipoRecurso === 'reporte' && recursos.reportes.length > 0 && !recursoId) {
            recursoId = String(recursos.reportes[0].id);
        } else if (tipoRecurso === 'programa' && recursos.programas.length > 0 && !recursoId) {
            recursoId = String(recursos.programas[0].id);
        } else if (tipoRecurso === 'apelacion' && recursos.apelaciones.length > 0 && !recursoId) {
            recursoId = String(recursos.apelaciones[0].id);
        }
    });

    async function ejecutarSimulacion() {
        cargando = true;
        try {
            const res = await fetch('/admin/abac/simular', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'Accept': 'application/json',
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
            } else {
                alert('Error al simular la política ABAC.');
            }
        } catch (e) {
            console.error(e);
            alert('Error de conexión.');
        } finally {
            cargando = false;
        }
    }
</script>

<AppHead title="Simulador ABAC" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <!-- Encabezado con métricas de arquitectura -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-4">
        <div>
            <PageHeader
                title="Simulador de Políticas ABAC"
                description="Auditoría y evaluación en tiempo real bajo el estándar NIST SP 800-162."
            />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="outline" class="font-mono text-xs border-primary/30 text-primary">
                {totalReglas} Reglas Activas
            </Badge>
            <Badge variant="outline" class="font-mono text-xs border-chart-2/40 text-chart-2">
                {denyByDefault ? 'Fail-Closed (Deny by Default)' : 'Permissive'}
            </Badge>
        </div>
    </div>

    <!-- Área principal: Formulario a la izquierda, resultados a la derecha -->
    <div class="grid gap-6 lg:grid-cols-12">
        <!-- FORMULARIO DE ENTRADA (4 cols) -->
        <div class="lg:col-span-5 space-y-5">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Cpu class="size-4 text-primary" />
                        Parámetros de la Evaluación
                    </CardTitle>
                    <CardDescription>
                        Selecciona el sujeto que solicita la acción, la operación a evaluar y el objeto de destino.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Sujeto / Usuario -->
                    <div class="space-y-1.5">
                        <label for="abac-usuario" class="text-xs font-semibold text-foreground">Sujeto (Usuario)</label>
                        <select
                            id="abac-usuario"
                            bind:value={usuarioSeleccionado}
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none"
                        >
                            <option value="">Invitado (No autenticado / Anónimo)</option>
                            {#each usuarios as u (u.id)}
                                <option value={String(u.id)}>
                                    {u.name} [{u.roles.join(', ')}] ({u.reputation_score} pts)
                                </option>
                            {/each}
                        </select>
                    </div>

                    <!-- Acción ABAC -->
                    <div class="space-y-1.5">
                        <label for="abac-accion" class="text-xs font-semibold text-foreground">Acción a Evaluar</label>
                        <select
                            id="abac-accion"
                            bind:value={accionSeleccionada}
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none font-mono"
                        >
                            {#each Object.entries(acciones) as [slug, label] (slug)}
                                <option value={slug}>
                                    {slug} — {label}
                                </option>
                            {/each}
                        </select>
                    </div>

                    <!-- Tipo de Recurso -->
                    <div class="space-y-1.5">
                        <label for="abac-tipo-recurso" class="text-xs font-semibold text-foreground">Tipo de Objeto / Recurso</label>
                        <select
                            id="abac-tipo-recurso"
                            bind:value={tipoRecurso}
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none"
                            onchange={() => recursoId = ''}
                        >
                            <option value="reporte">Reporte de Vulnerabilidad</option>
                            <option value="programa">Programa de Bug Bounty</option>
                            <option value="apelacion">Apelación de Sanción</option>
                            <option value="ninguno">Ninguno / Operación Global (sin objeto)</option>
                        </select>
                    </div>

                    <!-- Recurso Específico -->
                    {#if tipoRecurso !== 'ninguno'}
                        <div class="space-y-1.5">
                            <label for="abac-recurso-id" class="text-xs font-semibold text-foreground">Objeto Específico</label>
                            <select
                                id="abac-recurso-id"
                                bind:value={recursoId}
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none text-xs"
                            >
                                {#if tipoRecurso === 'reporte'}
                                    {#each recursos.reportes as r (r.id)}
                                        <option value={String(r.id)}>{r.etiqueta}</option>
                                    {/each}
                                {:else if tipoRecurso === 'programa'}
                                    {#each recursos.programas as p (p.id)}
                                        <option value={String(p.id)}>{p.etiqueta}</option>
                                    {/each}
                                {:else if tipoRecurso === 'apelacion'}
                                    {#each recursos.apelaciones as a (a.id)}
                                        <option value={String(a.id)}>{a.etiqueta}</option>
                                    {/each}
                                {/if}
                            </select>
                        </div>
                    {/if}

                    <Button class="w-full mt-2 font-semibold" onclick={ejecutarSimulacion} disabled={cargando}>
                        <Play class="mr-2 size-4" />
                        {cargando ? 'Evaluando motor...' : 'Ejecutar Simulación ABAC'}
                    </Button>
                </CardContent>
            </Card>

            <Card class="bg-muted/30">
                <CardContent class="pt-6 text-xs text-muted-foreground space-y-2">
                    <p class="font-semibold text-foreground flex items-center gap-1.5">
                        <Layers class="size-3.5 text-primary" />
                        Mapeo NIST SP 800-162
                    </p>
                    <p>
                        A diferencia de los sistemas tradicionales basados en roles (RBAC), el motor ABAC evalúa en tiempo real
                        atributos de tres fuentes: <strong>Sujeto</strong> (roles, reputación, estado de sanción),
                        <strong>Objeto</strong> (propiedad, programa, nivel de acceso) y <strong>Entorno</strong> (seguridad).
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- RESULTADOS DE LA SIMULACIÓN (7 cols) -->
        <div class="lg:col-span-7 space-y-5">
            {#if resultado}
                <!-- BANNER DE VEREDICTO FINAL -->
                <div class="rounded-xl border-2 {resultado.permitido ? 'border-primary bg-primary/10' : 'border-destructive bg-destructive/10'} p-5 shadow-lg space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            {#if resultado.permitido}
                                <ShieldCheck class="size-7 text-primary" />
                                <span class="text-xl font-extrabold text-foreground tracking-tight">ACCESO PERMITIDO</span>
                            {:else}
                                <ShieldAlert class="size-7 text-destructive" />
                                <span class="text-xl font-extrabold text-foreground tracking-tight">ACCESO DENEGADO</span>
                            {/if}
                        </div>
                        <Badge variant={resultado.permitido ? 'default' : 'destructive'} class="font-mono text-xs uppercase">
                            {resultado.decision}
                        </Badge>
                    </div>

                    <p class="text-sm font-medium text-foreground">
                        {resultado.motivo}
                    </p>

                    {#if resultado.regla_decisiva}
                        <div class="text-xs text-muted-foreground font-mono">
                            Regla de impacto: <strong class="text-foreground">[{resultado.regla_decisiva}]</strong>
                        </div>
                    {/if}
                </div>

                <!-- Selector de pestañas de análisis -->
                <div class="flex border-b border-border text-sm">
                    <button
                        type="button"
                        class="px-4 py-2 font-medium border-b-2 transition-colors {pestanaResultado === 'arbol' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'}"
                        onclick={() => pestanaResultado = 'arbol'}
                    >
                        Árbol de Decisión de Reglas ({resultado.detalle.length} evaluadas)
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 font-medium border-b-2 transition-colors {pestanaResultado === 'atributos' ? 'border-primary text-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'}"
                        onclick={() => pestanaResultado = 'atributos'}
                    >
                        Atributos Evaluados en Memoria
                    </button>
                </div>

                {#if pestanaResultado === 'arbol'}
                    <!-- LISTADO DE REGLAS EVALUADAS -->
                    <div class="space-y-3">
                        {#each resultado.detalle as item (item.regla)}
                            <div class="rounded-lg border {item.coincide ? (item.decision === 'permitir' ? 'border-primary/50 bg-primary/5' : 'border-destructive/50 bg-destructive/5') : 'border-border/60 bg-muted/20 opacity-70'} p-3.5 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        {#if item.coincide}
                                            {#if item.decision === 'permitir'}
                                                <CheckCircle2 class="size-4 text-primary" />
                                            {:else}
                                                <XCircle class="size-4 text-destructive" />
                                            {/if}
                                        {:else}
                                            <div class="size-4 rounded-full border border-muted-foreground/40 shrink-0"></div>
                                        {/if}
                                        <span class="font-mono font-bold {item.coincide ? 'text-foreground' : 'text-muted-foreground'}">
                                            [{item.regla}]
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Badge variant={item.decision === 'permitir' ? 'secondary' : 'destructive'} class="text-[10px] font-mono">
                                            {item.decision}
                                        </Badge>
                                        <Badge variant="outline" class="text-[10px]">
                                            {item.coincide ? 'Coincide' : 'No coincide'}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Condiciones por grupo -->
                                <div class="grid grid-cols-3 gap-2 text-[11px] pt-1">
                                    <div class="flex items-center gap-1 font-mono {item.grupos.sujeto ? 'text-primary' : 'text-muted-foreground'}">
                                        <span>Sujeto:</span>
                                        <strong>{item.grupos.sujeto ? 'OK' : 'No'}</strong>
                                    </div>
                                    <div class="flex items-center gap-1 font-mono {item.grupos.objeto ? 'text-primary' : 'text-muted-foreground'}">
                                        <span>Objeto:</span>
                                        <strong>{item.grupos.objeto ? 'OK' : 'No'}</strong>
                                    </div>
                                    <div class="flex items-center gap-1 font-mono {item.grupos.entorno ? 'text-primary' : 'text-muted-foreground'}">
                                        <span>Entorno:</span>
                                        <strong>{item.grupos.entorno ? 'OK' : 'No'}</strong>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <!-- JSON DE ATRIBUTOS -->
                    <div class="space-y-4">
                        <div class="rounded-lg border border-border bg-card p-4 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                                <Code2 class="size-3.5" />
                                Atributos del Sujeto ($sujeto)
                            </span>
                            <pre class="text-[11px] font-mono bg-muted/40 p-3 rounded overflow-x-auto text-foreground">{JSON.stringify(resultado.contexto.sujeto, null, 2)}</pre>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                                <Code2 class="size-3.5" />
                                Atributos del Objeto ($objeto)
                            </span>
                            <pre class="text-[11px] font-mono bg-muted/40 p-3 rounded overflow-x-auto text-foreground">{JSON.stringify(resultado.contexto.objeto, null, 2)}</pre>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                                <Code2 class="size-3.5" />
                                Atributos del Entorno ($entorno)
                            </span>
                            <pre class="text-[11px] font-mono bg-muted/40 p-3 rounded overflow-x-auto text-foreground">{JSON.stringify(resultado.contexto.entorno, null, 2)}</pre>
                        </div>
                    </div>
                {/if}
            {:else}
                <!-- ESTADO INICIAL VACÍO -->
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-border py-16 px-6 text-center space-y-3">
                    <div class="rounded-full bg-primary/10 p-4 text-primary">
                        <Cpu class="size-10" />
                    </div>
                    <h3 class="text-lg font-bold">Motor ABAC Listo para Simular</h3>
                    <p class="text-sm text-muted-foreground max-w-sm">
                        Selecciona a la izquierda un usuario, la acción a verificar y el recurso. Luego pulsa "Ejecutar Simulación ABAC" para visualizar el árbol de decisiones.
                    </p>
                </div>
            {/if}
        </div>
    </div>
</div>
