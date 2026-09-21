<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Ban from '@lucide/svelte/icons/ban';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import { estadoEmpresa, estiloRol, type CuentaEstado } from '@/lib/rangos';

    let { class: className = '' }: { class?: string } = $props();

    const cuenta = $derived(page.props.cuenta as CuentaEstado | null | undefined);
    const esInvestigador = $derived(cuenta?.roles.some((rol) => rol.slug === 'investigador') ?? false);
</script>

{#if cuenta}
    <div class="flex flex-wrap items-center gap-1.5 {className}" data-test="estado-cuenta-badges">
        {#each cuenta.roles as rol (rol.slug)}
            <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold {estiloRol(rol.slug)}" data-rol={rol.slug}>
                {rol.nombre}
            </span>
        {/each}

        {#if esInvestigador}
            <RangoBadge puntos={cuenta.reputacion} />
        {/if}

        {#if cuenta.empresa}
            {@const estado = estadoEmpresa(cuenta.empresa.estado)}
            <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold {estado.estilo}" title={cuenta.empresa.nombre}>
                Empresa {estado.etiqueta.toLowerCase()}
            </span>
        {/if}

        {#if cuenta.suspension}
            <span class="inline-flex items-center gap-1 rounded-md border border-rose-500/40 bg-rose-500/15 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:text-rose-300">
                <Ban class="size-3" aria-hidden="true" />
                Suspendido
            </span>
        {/if}
    </div>
{/if}
