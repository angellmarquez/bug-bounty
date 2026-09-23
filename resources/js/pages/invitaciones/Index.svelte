<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Invitaciones', href: '/invitaciones' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Mail from '@lucide/svelte/icons/mail';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

    type InvitacionPrograma = {
        id: number;
        programa_id: number;
        programa_nombre: string;
        programa_slug: string;
        empresa_nombre: string;
        invitado_por: string | null;
        estado: 'pendiente' | 'aceptada' | 'rechazada' | string;
        created_at: string;
    };

    let { invitacionesProgramas = [] }: { invitacionesProgramas?: InvitacionPrograma[] } = $props();

    let enviando = $state<number | null>(null);

    const etiquetas: Record<string, string> = { pendiente: 'Pendiente', aceptada: 'Aceptada', rechazada: 'Rechazada' };

    function responder(inv: InvitacionPrograma, accion: 'aceptar' | 'rechazar') {
        enviando = inv.id;
        router.post(`/invitaciones/programas/${inv.programa_id}/${accion}`, {}, { onFinish: () => (enviando = null) });
    }
</script>

<AppHead title="Invitaciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <PageHeader
        title="Invitaciones recibidas"
        description="Las empresas pueden invitarte a sus programas privados. Tú decides si aceptas."
    />

    {#if invitacionesProgramas.length === 0}
        <EmptyState
            icon={Mail}
            title="No tienes invitaciones"
            description="Cuando una empresa te invite a un programa privado, la verás aquí."
        />
    {:else}
        <div class="grid gap-4 sm:grid-cols-2">
            {#each invitacionesProgramas as inv (inv.id)}
                <div data-test="invitacion-programa"><Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle>{inv.programa_nombre}</CardTitle>
                                <CardDescription>Empresa: {inv.empresa_nombre}</CardDescription>
                            </div>
                            <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                                {etiquetas[inv.estado] ?? inv.estado}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            {inv.invitado_por ?? 'La empresa'} te invita a buscar vulnerabilidades en este programa privado.
                        </p>
                        <div class="flex gap-2">
                            {#if inv.estado === 'pendiente'}
                                <Button size="sm" disabled={enviando === inv.id} onclick={() => responder(inv, 'aceptar')}>
                                    Aceptar invitación
                                </Button>
                                <Button size="sm" variant="outline" disabled={enviando === inv.id} onclick={() => responder(inv, 'rechazar')}>
                                    Rechazar
                                </Button>
                            {:else if inv.estado === 'aceptada'}
                                <Button size="sm" variant="outline" href={`/programas/${inv.programa_id}`}>Ver programa</Button>
                            {/if}
                        </div>
                    </CardContent>
                </Card></div>
            {/each}
        </div>
    {/if}
</div>
