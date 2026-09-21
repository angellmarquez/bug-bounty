<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Medal from '@lucide/svelte/icons/medal';
    import { estiloRango, rangoDe, type ReputacionConfig } from '@/lib/rangos';

    let {
        puntos,
        mostrarPuntos = false,
        class: className = '',
    }: {
        puntos: number;
        mostrarPuntos?: boolean;
        class?: string;
    } = $props();

    const config = $derived(page.props.reputacionConfig as ReputacionConfig | undefined);
    const rango = $derived(rangoDe(puntos, config?.rangos ?? []));
</script>

<span
    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {estiloRango(rango.clave)} {className}"
    title="Rango {rango.nombre} · {puntos} pts"
    data-rango={rango.clave}
>
    <Medal class="size-3" aria-hidden="true" />
    {rango.nombre}
    {#if mostrarPuntos}<span class="font-normal opacity-80">· {puntos} pts</span>{/if}
</span>
