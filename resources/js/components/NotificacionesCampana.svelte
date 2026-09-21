<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Bell from '@lucide/svelte/icons/bell';
    import { estiloDeAviso, haceTiempo } from '@/lib/notificaciones';
    import type { ResumenNotificaciones } from '@/types/domain';

    const resumen = $derived(page.props.notificaciones as ResumenNotificaciones | null | undefined);

    let abierto = $state(false);
    let contenedor = $state<HTMLDivElement | undefined>();

    // Se refresca sola: pide solo el resumen, sin recargar la página.
    $effect(() => {
        if (!resumen) return;

        const temporizador = setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['notificaciones'] });
            }
        }, 30_000);

        return () => clearInterval(temporizador);
    });

    function alPulsarFuera(evento: MouseEvent) {
        if (abierto && contenedor && !contenedor.contains(evento.target as Node)) {
            abierto = false;
        }
    }

    function marcarTodas() {
        router.post('/notificaciones/leer-todas', {}, { preserveScroll: true });
    }
</script>

<svelte:window
    onclick={alPulsarFuera}
    onkeydown={(evento) => {
        if (evento.key === 'Escape') abierto = false;
    }}
/>

{#if resumen}
    <div class="relative" bind:this={contenedor}>
        <button
            type="button"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            aria-label="Notificaciones{resumen.no_leidas > 0 ? `, ${resumen.no_leidas} sin leer` : ''}"
            aria-expanded={abierto}
            data-test="campana"
            onclick={() => (abierto = !abierto)}
        >
            <Bell class="h-5 w-5" />
            {#if resumen.no_leidas > 0}
                <span
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white"
                    data-test="campana-contador"
                >
                    {resumen.no_leidas > 99 ? '99+' : resumen.no_leidas}
                </span>
            {/if}
        </button>

        {#if abierto}
            <div
                class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg"
                data-test="campana-panel"
            >
                <div class="flex items-center justify-between border-b px-3 py-2">
                    <p class="text-sm font-semibold">Notificaciones</p>
                    {#if resumen.no_leidas > 0}
                        <button type="button" class="text-xs text-primary hover:underline" onclick={marcarTodas} data-test="marcar-todas">
                            Marcar todas como leídas
                        </button>
                    {/if}
                </div>

                {#if resumen.recientes.length === 0}
                    <p class="px-3 py-8 text-center text-sm text-muted-foreground" data-test="campana-vacia">
                        No tienes notificaciones.
                    </p>
                {:else}
                    <ul class="max-h-96 divide-y overflow-y-auto">
                        {#each resumen.recientes as aviso (aviso.id)}
                            {@const estilo = estiloDeAviso(aviso.tipo)}
                            <li>
                                <Link
                                    href="/notificaciones/{aviso.id}/abrir"
                                    class="flex gap-3 px-3 py-2.5 transition-colors hover:bg-accent {aviso.leida ? 'opacity-70' : ''}"
                                    onclick={() => (abierto = false)}
                                    data-test="campana-aviso"
                                >
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {estilo.clase}">
                                        <estilo.icono class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-start justify-between gap-2">
                                            <span class="text-sm font-medium leading-tight">{aviso.titulo}</span>
                                            {#if !aviso.leida}
                                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-label="Sin leer"></span>
                                            {/if}
                                        </span>
                                        <span class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">{aviso.mensaje}</span>
                                        <span class="mt-1 block text-[11px] text-muted-foreground">{haceTiempo(aviso.created_at)}</span>
                                    </span>
                                </Link>
                            </li>
                        {/each}
                    </ul>
                {/if}

                <div class="border-t px-3 py-2 text-center">
                    <Link href="/notificaciones" class="text-xs text-primary hover:underline" onclick={() => (abierto = false)} data-test="ver-todas-avisos">
                        Ver todas
                    </Link>
                </div>
            </div>
        {/if}
    </div>
{/if}
