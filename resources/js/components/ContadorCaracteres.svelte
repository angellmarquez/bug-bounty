<script lang="ts">
    // Cuántos caracteres lleva un campo de texto largo frente al límite del servidor.
    let {
        actual,
        maximo,
        test = undefined,
    }: {
        actual: number;
        maximo: number;
        test?: string;
    } = $props();

    const cerca = $derived(actual >= maximo * 0.9);
</script>

<p class="text-right text-xs {cerca ? 'text-aviso' : 'text-muted-foreground'}" data-test={test} aria-live="polite">
    {actual} / {maximo}
</p>
