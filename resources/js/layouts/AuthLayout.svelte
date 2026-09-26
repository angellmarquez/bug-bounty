<script lang="ts">
    import type { Snippet } from 'svelte';
    import AuthPortalLayout from '@/layouts/auth/AuthPortalLayout.svelte';

    // Cada página de acceso declara en su `layout` para quién es (portal) y qué pantalla es (modo).
    let {
        title = '',
        description = '',
        portal = 'investigador',
        modo = 'otro',
        children,
    }: {
        title?: string;
        description?: string;
        portal?: 'investigador' | 'empresa';
        modo?: 'login' | 'registro' | 'otro';
        children?: Snippet;
    } = $props();
</script>

<AuthPortalLayout {title} {description} {portal} {modo}>
    {@render children?.()}
</AuthPortalLayout>
