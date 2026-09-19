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
    import { Link } from '@inertiajs/svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Settings from '@lucide/svelte/icons/settings';
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
            programas: { id: number; nombre: string; estado: string }[];
            reportes: {
                id: number;
                numero_reporte: string;
                titulo: string;
                estado: string;
                severidad: string | null;
                programa_id: number;
                programa_nombre: string;
                investigador: { id: number; name: string } | null;
                poc: Record<string, unknown> | null;
            }[];
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
    >
        {#if empresa.puedeOperar}
            <Button href="/gestion/programas/crear">
                <Plus class="mr-2 h-4 w-4" />
                Crear programa
            </Button>
        {/if}
    </PageHeader>

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

    {#if empresa.puedeOperar}
        <Card>
            <CardHeader>
                <CardTitle>Programas publicados</CardTitle>
                <CardDescription>Programas que los investigadores pueden consultar.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2">
                {#if empresa.programas.length > 0}
                    <Button variant="outline" href="/gestion/programas" class="mb-2">
                        <Settings class="mr-2 h-4 w-4" />
                        Gestionar programas
                    </Button>
                {/if}
                {#if empresa.programas.length === 0}
                    <p class="text-sm text-muted-foreground">Todavia no hay programas.</p>
                {:else}
                    {#each empresa.programas as programa (programa.id)}
                        <Link href={`/programas/${programa.id}`} class="flex items-center justify-between rounded-md border p-3 hover:bg-muted">
                            <span class="font-medium">{programa.nombre}</span>
                            <span class="text-xs text-muted-foreground">{programa.estado}</span>
                        </Link>
                    {/each}
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Reportes recibidos</CardTitle>
                <CardDescription>Reportes enviados por investigadores a tus programas. Los borradores no aparecen.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                {#if empresa.reportes.length === 0}
                    <p class="text-sm text-muted-foreground">Todavia no hay reportes recibidos.</p>
                {:else}
                    {#each empresa.reportes as reporte (reporte.id)}
                        <Link href={`/reportes/${reporte.id}`} class="block rounded-md border p-3 hover:bg-muted">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">{reporte.numero_reporte} - {reporte.titulo}</span>
                                <div class="flex gap-2">
                                    <StateBadge estado={reporte.estado} />
                                    {#if reporte.severidad}<SeverityBadge severidad={reporte.severidad} />{/if}
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {reporte.programa_nombre} · Investigador: {reporte.investigador?.name ?? 'N/D'}
                            </p>
                            {#if reporte.poc}
                                <p class="mt-2 text-xs text-muted-foreground">Incluye prueba de concepto</p>
                            {/if}
                        </Link>
                    {/each}
                {/if}
            </CardContent>
        </Card>
    {/if}
</div>