<script lang="ts">
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

    const entradas = $derived(entradasPoc(poc, pocSchema));
    const categoriaLegible = $derived(
        CATEGORIAS_REPORTE.find((opcion) => opcion.value === categoria)?.label ?? categoria,
    );
    const hayClasificacion = $derived(Boolean(categoria || vectorCvss || puntuacionCvss));
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
    {/if}
</div>
