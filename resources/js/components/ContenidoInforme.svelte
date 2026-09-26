<script lang="ts">
    import Terminal from '@lucide/svelte/icons/terminal';
    import Copy from '@lucide/svelte/icons/copy';
    import Check from '@lucide/svelte/icons/check';
    import { Button } from '@/components/ui/button';
    import { CATEGORIAS_REPORTE } from '@/lib/categorias-reporte';
    import { entradasPoc, esUrlSegura } from '@/lib/poc-display';
    import type { PocSchemaField } from '@/types/domain';

    let {
        descripcion,
        poc = null,
        pocSchema = null,
        categoria = null,
        vectorCvss = null,
        puntuacionCvss = null,
        cifradoIndisponible = false,
    }: {
        descripcion: string | null;
        poc?: Record<string, unknown> | null;
        pocSchema?: PocSchemaField[] | null;
        categoria?: string | null;
        vectorCvss?: string | null;
        puntuacionCvss?: number | string | null;
        cifradoIndisponible?: boolean;
    } = $props();

    let copiado = $state(false);

    const entradas = $derived(entradasPoc(poc, pocSchema));
    const categoriaLegible = $derived(
        CATEGORIAS_REPORTE.find((opcion) => opcion.value === categoria)?.label ?? categoria,
    );
    const hayClasificacion = $derived(Boolean(categoria || vectorCvss || puntuacionCvss));

    const comandoCurl = $derived.by(() => {
        if (!poc) return null;

        // Si ya hay un comando cURL explícito:
        if (typeof poc.request_curl === 'string' && poc.request_curl.trim().startsWith('curl')) {
            return poc.request_curl.trim();
        }

        // Buscar una URL segura en los campos del PoC:
        let urlTarget: string | null = null;
        for (const [k, v] of Object.entries(poc)) {
            if (typeof v === 'string' && esUrlSegura(v)) {
                urlTarget = v;
                break;
            }
        }

        if (!urlTarget) return null;

        const metodo = typeof poc.metodo_http === 'string' ? poc.metodo_http.toUpperCase() : 'GET';
        const payload = typeof poc.payload_request === 'string' ? poc.payload_request.trim() : null;

        if (payload) {
            const safePayload = payload.replace(/"/g, '\\"');
            return `curl -i -X ${metodo} "${urlTarget}" \\\n  -H "Content-Type: application/json" \\\n  -d "${safePayload}"`;
        }

        return `curl -i -X ${metodo} "${urlTarget}"`;
    });

    async function copiarComando() {
        if (!comandoCurl) return;
        try {
            await navigator.clipboard.writeText(comandoCurl);
            copiado = true;
            setTimeout(() => {
                copiado = false;
            }, 2000);
        } catch {
            // Fallback si el portapapeles no está disponible
        }
    }
</script>

<div class="space-y-5">
    {#if cifradoIndisponible}
        <p role="alert" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            No se pudo descifrar el contenido del informe en este momento. Vuelve a intentarlo en unos segundos; si
            persiste, el administrador puede revisar el estado del cifrado en Claves PGP.
        </p>
    {:else}
        <section class="space-y-1">
            <h4 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Descripción del hallazgo</h4>
            <p class="whitespace-pre-wrap break-words text-sm">{descripcion || 'Sin descripción'}</p>
        </section>

        {#if hayClasificacion}
            <section class="space-y-2">
                <h4 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Clasificación</h4>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Categoría</dt>
                        <dd>{categoriaLegible ?? 'Sin categoría'}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Puntuación CVSS</dt>
                        <dd>{puntuacionCvss ?? 'Sin calcular'}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-muted-foreground">Vector CVSS</dt>
                        <dd class="break-all font-mono text-xs">{vectorCvss ?? 'Sin calcular'}</dd>
                    </div>
                </dl>
            </section>
        {/if}

        <section class="space-y-2">
            <h4 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Prueba de concepto (PoC)</h4>
            {#if entradas.length === 0}
                <p class="text-sm text-muted-foreground">El investigador no adjuntó prueba de concepto.</p>
            {:else}
                <dl class="space-y-3">
                    {#each entradas as entrada (entrada.clave)}
                        <div class="space-y-1">
                            <dt class="text-xs font-medium text-muted-foreground">{entrada.etiqueta}</dt>
                            {#each entrada.valores as valor, i (i)}
                                <dd>
                                    {#if entrada.tipo === 'code' || valor.includes('\n')}
                                        <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-muted p-3 text-xs">{valor}</pre>
                                    {:else if entrada.tipo === 'url' && esUrlSegura(valor)}
                                        <a
                                            href={valor}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="break-all text-sm text-primary hover:underline"
                                        >{valor}</a>
                                    {:else}
                                        <span class="break-words text-sm">{valor}</span>
                                    {/if}
                                </dd>
                            {/each}
                        </div>
                    {/each}
                </dl>
            {/if}
        </section>

        {#if comandoCurl}
            <section class="space-y-2 rounded-lg border border-border bg-muted/30 p-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Terminal class="h-4 w-4 text-primary" />
                        <h4 class="text-xs font-semibold uppercase tracking-wide">Comando de Reproducción (cURL)</h4>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs gap-1"
                        onclick={copiarComando}
                    >
                        {#if copiado}
                            <Check class="h-3.5 w-3.5 text-emerald-500" />
                            <span>Copiado</span>
                        {:else}
                            <Copy class="h-3.5 w-3.5" />
                            <span>Copiar cURL</span>
                        {/if}
                    </Button>
                </div>
                <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded-md bg-neutral-950 p-2.5 font-mono text-xs text-emerald-400 dark:bg-black">{comandoCurl}</pre>
                <p class="text-[11px] text-muted-foreground">
                    ⚠️ <strong>Uso seguro:</strong> Ejecutar únicamente en un entorno de pruebas o laboratorio autorizado para verificar el hallazgo de forma aislada.
                </p>
            </section>
        {/if}
    {/if}
</div>
