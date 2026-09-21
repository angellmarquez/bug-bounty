<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Lock from '@lucide/svelte/icons/lock';
    import { estiloRango, type NivelAcceso, type ReputacionConfig } from '@/lib/rangos';

    let {
        nivel,
        class: className = '',
    }: {
        nivel: NivelAcceso;
        class?: string;
    } = $props();

    const config = $derived(page.props.reputacionConfig as ReputacionConfig | undefined);
    const info = $derived(config?.niveles.find((n) => n.valor === nivel));
</script>

{#if info}
    <span
        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {estiloRango(info.rango)} {className}"
        title="Exige rango {info.rangoNombre} ({info.minimo}+ pts)"
        data-nivel={nivel}
    >
        <Lock class="size-3" aria-hidden="true" />
        Acceso {info.etiqueta.toLowerCase()} · {info.rangoNombre}
    </span>
{/if}
