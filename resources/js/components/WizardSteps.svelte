<script lang="ts">
    import Check from '@lucide/svelte/icons/check';

    let {
        pasos = [
            'Detalles',
            'CVSS',
            'PoC',
            'PGP',
            'Revision',
        ],
        pasoActual = 1,
    }: {
        pasos?: string[];
        pasoActual?: number;
    } = $props();
</script>

<nav class="flex items-center justify-center" aria-label="Progreso del wizard">
    <ol class="flex items-center gap-0">
        {#each pasos as paso, i (i)}
            {@const numero = i + 1}
            {@const completado = numero < pasoActual}
            {@const actual = numero === pasoActual}

            <li class="flex items-center">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold transition-colors
                        {completado
                            ? 'bg-chart-1 text-white'
                            : actual
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'}"
                    >
                        {#if completado}
                            <Check class="h-4 w-4" />
                        {:else}
                            {numero}
                        {/if}
                    </div>
                    <span
                        class="hidden text-sm font-medium sm:inline
                        {actual ? 'text-foreground' : completado ? 'text-chart-1' : 'text-muted-foreground'}"
                    >
                        {paso}
                    </span>
                </div>
                {#if i < pasos.length - 1}
                    <div
                        class="mx-2 h-px w-6 sm:w-12
                        {completado ? 'bg-chart-1' : 'bg-border'}"
                    ></div>
                {/if}
            </li>
        {/each}
    </ol>
</nav>
