<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Empresa', href: '/empresa' }],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

    let { empresa }: {
        empresa: {
            id: number;
            razon_social: string;
            nombre_comercial: string | null;
            identificador_fiscal: string;
            email: string;
            estado: string;
            motivo_estado: string | null;
            rol_interno: string;
            puedeOperar: boolean;
            usuarios: { id: number; name: string; email: string }[];
            invitaciones: { id: number; email: string; expira_en: string }[];
        };
    } = $props();

    let emailMiembro = $state('');
    let emailInvitacion = $state('');
    let invitacionUrl = $state('');

    function agregarMiembro() {
        router.post('/empresa/miembros', { email: emailMiembro }, {
            preserveState: true,
            onSuccess: () => { emailMiembro = ''; },
        });
    }

    function eliminarMiembro(userId: number) {
        router.delete(`/empresa/miembros/${userId}`, { preserveState: true });
    }

    function invitarMiembro() {
        router.post('/empresa/invitaciones', { email: emailInvitacion }, {
            preserveState: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { invitacion_url?: string } | undefined;
                invitacionUrl = String(flash?.invitacion_url ?? '');
                emailInvitacion = '';
            },
        });
    }
</script>

<AppHead title="Empresa" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <PageHeader
        title={empresa.nombre_comercial ?? empresa.razon_social}
        description="Estado de la solicitud empresarial"
    />

    <Card>
        <CardHeader>
            <CardTitle>Solicitud {empresa.estado}</CardTitle>
            <CardDescription>
                {#if empresa.puedeOperar}
                    La empresa está aprobada y puede operar sus programas.
                {:else}
                    El equipo administrador revisará los datos antes de habilitar la operación.
                {/if}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
            <p><span class="text-muted-foreground">Razón social:</span> {empresa.razon_social}</p>
            <p><span class="text-muted-foreground">Identificador fiscal:</span> {empresa.identificador_fiscal}</p>
            <p><span class="text-muted-foreground">Correo:</span> {empresa.email}</p>
            <p><span class="text-muted-foreground">Rol:</span> {empresa.rol_interno}</p>
            {#if empresa.motivo_estado}
                <p class="border-t border-border pt-3"><span class="text-muted-foreground">Motivo:</span> {empresa.motivo_estado}</p>
            {/if}
        </CardContent>
    </Card>

    {#if empresa.puedeOperar && empresa.rol_interno === 'propietario'}
        <Card>
            <CardHeader><CardTitle>Miembros</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <form class="flex gap-2" onsubmit={(event) => { event.preventDefault(); agregarMiembro(); }}>
                    <Input type="email" bind:value={emailMiembro} placeholder="correo@empresa.com" required />
                    <Button type="submit">Agregar</Button>
                </form>
                <form class="flex gap-2" onsubmit={(event) => { event.preventDefault(); invitarMiembro(); }}>
                    <Input type="email" bind:value={emailInvitacion} placeholder="Invitar por correo" required />
                    <Button type="submit" variant="outline">Invitar</Button>
                </form>
                {#if invitacionUrl}
                    <p class="break-all rounded-md bg-muted p-2 text-xs">Enlace de invitación: <a href={invitacionUrl} class="text-primary underline">{invitacionUrl}</a></p>
                {/if}
                <div class="space-y-2">
                    {#each empresa.usuarios as usuario (usuario.id)}
                        <div class="flex items-center justify-between gap-3 border-b border-border py-2 last:border-0">
                            <div><p class="text-sm font-medium">{usuario.name}</p><p class="text-xs text-muted-foreground">{usuario.email}</p></div>
                            {#if usuario.email !== empresa.email}<Button size="sm" variant="destructive" onclick={() => eliminarMiembro(usuario.id)}>Retirar</Button>{/if}
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}
</div>