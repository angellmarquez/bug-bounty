<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import ErrorDePagina from '@/components/ErrorDePagina.svelte';
    import { page } from '@inertiajs/svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    // La barrera se reinicia al cambiar de ruta (no con los filtros de la misma página).
    const rutaActual = $derived(page.url.split('?')[0]);
</script>

<AppShell variant="sidebar">
    <AppSidebar />
    <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
        <svelte:boundary onerror={(error) => console.error('Error en la cabecera:', error)}>
            <AppSidebarHeader {breadcrumbs} />
            {#snippet failed()}
                <div class="h-16 shrink-0 border-b"></div>
            {/snippet}
        </svelte:boundary>
        {#key rutaActual}
            <svelte:boundary onerror={(error) => console.error('Error en la página:', error)}>
                {@render children?.()}
                {#snippet failed(error, reset)}
                    <ErrorDePagina {error} {reset} />
                {/snippet}
            </svelte:boundary>
        {/key}
    </AppContent>
    <Toaster />
</AppShell>
