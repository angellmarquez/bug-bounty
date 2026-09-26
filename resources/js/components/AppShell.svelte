<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { untrack, type Snippet } from 'svelte';
    import { SidebarProvider } from '@/components/ui/sidebar';
    import { esTema, themeState } from '@/lib/theme.svelte';
    import type { AppVariant } from '@/types';

    let {
        variant = 'sidebar',
        class: className = '',
        children,
    }: {
        variant?: AppVariant;
        class?: string;
        children?: Snippet;
    } = $props();

    const isOpen = $derived(page.props.sidebarOpen);

    // El tema guardado en la cuenta manda: al iniciar sesión sin recargar se aplica aquí.
    const { tema, updateTema } = themeState();
    const temaDeCuenta = $derived(page.props.auth?.user?.tema);
    $effect(() => {
        const deCuenta = temaDeCuenta;
        // Solo reacciona a cambios del tema de la cuenta, no a la elección local mientras se guarda.
        untrack(() => {
            if (esTema(deCuenta) && deCuenta !== tema.value) {
                updateTema(deCuenta);
            }
        });
    });
</script>

{#if variant === 'header'}
    <div class="flex min-h-screen w-full flex-col {className}">
        {@render children?.()}
    </div>
{:else}
    <SidebarProvider defaultOpen={isOpen}>
        {@render children?.()}
    </SidebarProvider>
{/if}
