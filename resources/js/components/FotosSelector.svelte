<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ImagePlus from '@lucide/svelte/icons/image-plus';
    import X from '@lucide/svelte/icons/x';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { formatearTamano } from '@/lib/fotos';

    // Selector de fotos de evidencia: valida tipo, peso y cantidad antes de subir.
    // El servidor vuelve a validar todo; esto solo ahorra subidas que fallarían.
    let {
        archivos = $bindable([]),
        yaAdjuntas = 0,
        error = '',
        id = 'fotos',
    }: {
        archivos?: File[];
        yaAdjuntas?: number;
        error?: string;
        id?: string;
    } = $props();

    const limites = $derived(
        page.props.limitesFotos ?? { max: 10, max_kb: 5120, max_total_kb: 8192, mimes: ['image/png', 'image/jpeg', 'image/webp'] },
    );
    const restantes = $derived(Math.max(0, limites.max - yaAdjuntas - archivos.length));

    let aviso = $state('');
    let arrastrando = $state(false);
    let input: HTMLInputElement | undefined = $state();

    // Una URL de vista previa por archivo; se liberan al quitar el archivo o desmontar el componente.
    const previews = $derived(archivos.map((archivo) => URL.createObjectURL(archivo)));
    $effect(() => {
        const urls = previews;

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    });

    function agregar(lista: FileList | null) {
        if (!lista) return;
        aviso = '';
        const nuevos: File[] = [];
        const rechazados: string[] = [];
        let total = archivos.reduce((suma, archivo) => suma + archivo.size, 0);

        for (const archivo of Array.from(lista)) {
            if (!limites.mimes.includes(archivo.type)) {
                rechazados.push(`«${archivo.name}» no es PNG, JPG o WebP`);
            } else if (archivo.size > limites.max_kb * 1024) {
                rechazados.push(`«${archivo.name}» pesa más de ${formatearTamano(limites.max_kb * 1024)}`);
            } else if (nuevos.length >= restantes) {
                rechazados.push(`«${archivo.name}» supera el máximo de ${limites.max} fotos`);
            } else if (total + archivo.size > limites.max_total_kb * 1024) {
                rechazados.push(`«${archivo.name}» no cabe: todas las fotos juntas pueden pesar hasta ${formatearTamano(limites.max_total_kb * 1024)} por envío`);
            } else {
                nuevos.push(archivo);
                total += archivo.size;
            }
        }

        archivos = [...archivos, ...nuevos];
        aviso = rechazados.join('. ');
        if (input) input.value = '';
    }

    function quitar(indice: number) {
        archivos = archivos.filter((_, i) => i !== indice);
        aviso = '';
    }

    function alSoltar(e: DragEvent) {
        e.preventDefault();
        arrastrando = false;
        agregar(e.dataTransfer?.files ?? null);
    }
</script>

<div class="space-y-3" data-test="fotos-selector">
    <label
        for={id}
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center transition-colors hover:bg-muted/50 {arrastrando ? 'border-primary bg-primary/5' : 'border-border'} {restantes === 0 ? 'pointer-events-none opacity-50' : ''}"
        ondragover={(e) => { e.preventDefault(); arrastrando = true; }}
        ondragleave={() => (arrastrando = false)}
        ondrop={alSoltar}
    >
        <ImagePlus class="size-6 text-muted-foreground" />
        <span class="text-sm">Arrastra fotos aquí o haz clic para elegirlas</span>
        <span class="text-xs text-muted-foreground">
            PNG, JPG o WebP · hasta {formatearTamano(limites.max_kb * 1024)} cada una · te quedan {restantes} de {limites.max}
        </span>
        <input
            bind:this={input}
            {id}
            type="file"
            accept={limites.mimes.join(',')}
            multiple
            class="sr-only"
            disabled={restantes === 0}
            onchange={(e) => agregar(e.currentTarget.files)}
        />
    </label>

    <p class="text-xs text-muted-foreground">
        Las fotos se cifran antes de guardarse y se les quitan los metadatos (ubicación GPS, modelo del dispositivo).
    </p>

    {#if archivos.length > 0}
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {#each archivos as archivo, i (i)}
                <li class="relative overflow-hidden rounded-md border border-border" data-test="foto-seleccionada">
                    <img src={previews[i]} alt={archivo.name} class="aspect-square w-full object-cover" />
                    <div class="truncate px-2 py-1 text-xs text-muted-foreground" title={archivo.name}>
                        {archivo.name} · {formatearTamano(archivo.size)}
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="absolute top-1 right-1 size-7"
                        aria-label="Quitar {archivo.name}"
                        onclick={() => quitar(i)}
                    >
                        <X class="size-4" />
                    </Button>
                </li>
            {/each}
        </ul>
    {/if}

    <InputError message={aviso || error} />
</div>
