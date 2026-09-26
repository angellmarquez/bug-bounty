<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import MonitorSmartphone from '@lucide/svelte/icons/monitor-smartphone';
    import { themeState, type Tema, type TemaResuelto } from '@/lib/theme.svelte';

    const { tema, updateTema } = themeState();

    const opciones: { valor: Tema; nombre: string; descripcion: string; muestra: TemaResuelto | null }[] = [
        { valor: 'terminal', nombre: 'Terminal', descripcion: 'Oscuro y verde. El tema por defecto.', muestra: 'terminal' },
        { valor: 'corporativo', nombre: 'Corporativo', descripcion: 'Claro e índigo, sobrio.', muestra: 'corporativo' },
        { valor: 'neon', nombre: 'Neón', descripcion: 'Oscuro, cian y magenta, con brillo.', muestra: 'neon' },
        { valor: 'auto', nombre: 'Automático', descripcion: 'Terminal o Corporativo según el modo de tu sistema.', muestra: null },
    ];

    let guardando = $state(false);

    function elegir(valor: Tema) {
        updateTema(valor);

        // Con sesión iniciada se guarda también en la cuenta.
        if (!page.props.auth?.user) return;
        guardando = true;
        router.put('/settings/appearance', { tema: valor }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (guardando = false),
        });
    }
</script>

<div class="grid gap-4 sm:grid-cols-2" role="radiogroup" aria-label="Tema visual" data-test="tema-selector">
    {#each opciones as opcion (opcion.valor)}
        {@const activo = tema.value === opcion.valor}
        <button
            type="button"
            role="radio"
            aria-checked={activo}
            disabled={guardando}
            onclick={() => elegir(opcion.valor)}
            data-test="tema-{opcion.valor}"
            class="group relative overflow-hidden rounded-xl border text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring {activo ? 'border-primary ring-1 ring-primary' : 'border-border hover:border-primary/50'}"
        >
            {#if opcion.muestra}
                <!-- Miniatura con los colores reales del tema. -->
                <div data-tema={opcion.muestra} class="flex h-28 gap-2 bg-background p-3" aria-hidden="true">
                    <div class="flex w-10 flex-col gap-1.5 rounded-md bg-sidebar p-1.5">
                        <div class="size-3 rounded-sm bg-sidebar-primary"></div>
                        <div class="h-1.5 rounded-full bg-sidebar-foreground/40"></div>
                        <div class="h-1.5 rounded-full bg-sidebar-foreground/25"></div>
                        <div class="h-1.5 rounded-full bg-sidebar-foreground/25"></div>
                    </div>
                    <div class="flex flex-1 flex-col gap-2">
                        <div class="h-2.5 w-2/3 rounded-full bg-foreground/80"></div>
                        <div class="flex flex-1 flex-col justify-between rounded-md border border-border bg-card p-2">
                            <div class="h-1.5 w-3/4 rounded-full bg-muted-foreground/50"></div>
                            <div class="flex gap-1.5">
                                <div class="h-3 w-10 rounded-sm bg-primary"></div>
                                <div class="h-3 w-6 rounded-sm bg-exito/30"></div>
                                <div class="h-3 w-6 rounded-sm bg-peligro/30"></div>
                            </div>
                        </div>
                    </div>
                </div>
            {:else}
                <div class="flex h-28" aria-hidden="true">
                    <div data-tema="terminal" class="flex flex-1 items-center justify-center bg-background">
                        <div class="h-3 w-10 rounded-sm bg-primary"></div>
                    </div>
                    <div data-tema="corporativo" class="flex flex-1 items-center justify-center bg-background">
                        <div class="h-3 w-10 rounded-sm bg-primary"></div>
                    </div>
                </div>
            {/if}

            <div class="flex items-start justify-between gap-3 border-t border-border bg-card p-3">
                <div class="space-y-0.5">
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        {#if !opcion.muestra}<MonitorSmartphone class="size-3.5" aria-hidden="true" />{/if}
                        {opcion.nombre}
                    </p>
                    <p class="text-xs text-muted-foreground">{opcion.descripcion}</p>
                </div>
                {#if activo}
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                        <Check class="size-3" aria-hidden="true" />
                    </span>
                {/if}
            </div>
        </button>
    {/each}
</div>
