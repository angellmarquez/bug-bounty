<script lang="ts">
    import ImageOff from '@lucide/svelte/icons/image-off';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { Button } from '@/components/ui/button';
    import { formatearTamano, huellaCorta } from '@/lib/fotos';
    import type { FotoAdjunta } from '@/types/domain';

    // Fotos de evidencia ya guardadas. Cada imagen se pide a una ruta que valida el
    // acceso y la descifra; no hay URL pública.
    let {
        fotos,
        onEliminar,
        eliminando = null,
    }: {
        fotos: FotoAdjunta[];
        onEliminar?: (foto: FotoAdjunta) => void;
        eliminando?: number | null;
    } = $props();

    let fallidas = $state<Record<number, boolean>>({});
</script>

{#if fotos.length === 0}
    <p class="text-sm text-muted-foreground">No hay fotos adjuntas.</p>
{:else}
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-test="galeria-fotos">
        {#each fotos as foto (foto.id)}
            <li class="overflow-hidden rounded-md border border-border" data-test="foto-adjunta">
                <a href={foto.url} target="_blank" rel="noopener noreferrer" class="block bg-muted">
                    {#if fallidas[foto.id]}
                        <div class="flex aspect-square items-center justify-center text-muted-foreground">
                            <ImageOff class="size-6" />
                        </div>
                    {:else}
                        <img
                            src={foto.url}
                            alt={foto.nombre}
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="aspect-square w-full object-cover"
                            onerror={() => (fallidas[foto.id] = true)}
                        />
                    {/if}
                </a>
                <div class="space-y-0.5 px-2 py-1.5 text-xs">
                    <p class="truncate" title={foto.nombre}>{foto.nombre}</p>
                    <p class="text-muted-foreground">{foto.ancho}×{foto.alto} · {formatearTamano(foto.tamano)}</p>
                    <p class="font-mono text-muted-foreground" title="SHA-256: {foto.sha256}">SHA-256 {huellaCorta(foto)}</p>
                    {#if onEliminar}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="mt-1 h-7 w-full text-destructive"
                            disabled={eliminando === foto.id}
                            onclick={() => onEliminar(foto)}
                        >
                            <Trash2 class="mr-1 size-3.5" />
                            Quitar
                        </Button>
                    {/if}
                </div>
            </li>
        {/each}
    </ul>
{/if}
