<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Invitación', href: '/empresa' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

    let { invitacion }: {
        invitacion: {
            token: string;
            email: string;
            expira_en: string;
            empresa: { razon_social: string; nombre_comercial: string | null };
        };
    } = $props();

    function aceptar() {
        router.post(`/empresa/invitacion/${invitacion.token}/aceptar`);
    }
</script>

<AppHead title="Invitación empresarial" />

<div class="flex min-h-svh items-center justify-center p-4">
    <Card class="w-full max-w-lg">
        <CardHeader>
            <CardTitle>Invitación empresarial</CardTitle>
            <CardDescription>
                Has sido invitado a unirte a {invitacion.empresa.nombre_comercial ?? invitacion.empresa.razon_social}.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <p class="text-sm text-muted-foreground">La invitación está dirigida a {invitacion.email} y expira el {new Date(invitacion.expira_en).toLocaleDateString('es-ES')}.</p>
            <Button onclick={aceptar}>Aceptar invitación</Button>
        </CardContent>
    </Card>
</div>