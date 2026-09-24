<script module lang="ts">
    import { edit } from '@/routes/security';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ManageTwoFactor from '@/components/ManageTwoFactor.svelte';

    // Solo verificación en dos pasos: la contraseña no se cambia desde la app y no hay passkeys.
    const canManageTwoFactor = $derived(Boolean(page.props.canManageTwoFactor));
    const requiresConfirmation = $derived(
        Boolean(page.props.requiresConfirmation),
    );
    const twoFactorEnabled = $derived(Boolean(page.props.twoFactorEnabled));
</script>

<AppHead title="Security settings" />

<h1 class="sr-only">Security settings</h1>

<ManageTwoFactor
    {canManageTwoFactor}
    {requiresConfirmation}
    {twoFactorEnabled}
/>
