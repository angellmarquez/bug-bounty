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
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

    type Invitacion = {
        id: number;
        estado: 'pendiente' | 'aceptada' | 'rechazada' | 'cancelada' | string;
        vigente: boolean;
        expira_en: string;
        respondida_en: string | null;
        empresa: { nombre: string; sitio_web: string | null };
        invitada_por: string | null;
    };

    let {
        invitaciones,
        empresaActual,
    }: {
        invitaciones: Invitacion[];
        empresaActual: { nombre: string; rol_interno: string } | null;
    } = $props();

    let enviando = $state<number | null>(null);

    const vigentes = $derived(invitaciones.filter((invitacion) => invitacion.vigente));
    const historial = $derived(invitaciones.filter((invitacion) => !invitacion.vigente));

    function fecha(iso: string | null): string {
        if (!iso) return '';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(iso));
    }

    function estadoDe(invitacion: Invitacion): string {
        if (invitacion.estado === 'pendiente') return 'Caducada';
        return { aceptada: 'Aceptada', rechazada: 'Rechazada', cancelada: 'Cancelada' }[invitacion.estado] ?? invitacion.estado;
    }

    function aceptar(invitacion: Invitacion) {
        if (
            !confirm(
                `¿Unirte a ${invitacion.empresa.nombre}? Podrás publicar sus programas, pero mientras seas miembro no podrás reportar a sus programas.`,
            )
        )
            return;
        enviar(invitacion.id, 'aceptar');
    }

    function enviar(id: number, accion: 'aceptar' | 'rechazar') {
        enviando = id;
        router.post(`/invitaciones/${id}/${accion}`, {}, { onFinish: () => (enviando = null) });
    }
</script>

<AppHead title="Invitaciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Invitaciones a empresas"
        description="Una empresa puede invitarte a publicar sus programas. Tú decides si aceptas."
    />

    {#if empresaActual}
        <div data-test="empresa-actual"><Card class="border-sky-500/40">
            <CardContent class="pt-6 text-sm">
                Ya formas parte de <span class="font-medium">{empresaActual.nombre}</span> como {empresaActual.rol_interno}. Solo puedes
                pertenecer a una empresa: para aceptar otra invitación primero tendría que retirarte la actual.
            </CardContent>
        </Card></div>
    {/if}

    {#if vigentes.length === 0 && historial.length === 0}
        <EmptyState
            icon={Mail}
            title="No tienes invitaciones"
            description="Cuando una empresa te invite a publicar sus programas, te avisaremos en la campana y la verás aquí."
        />
    {/if}

    {#each vigentes as invitacion (invitacion.id)}
        <div data-test="invitacion"><Card>
            <CardHeader>
                <CardTitle>{invitacion.empresa.nombre}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    {invitacion.invitada_por ?? 'El propietario'} te invita a publicar programas en su nombre · vence el {fecha(invitacion.expira_en)}
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                    <li>Podrás <span class="text-foreground">crear, editar y publicar</span> los programas de la empresa.</li>
                    <li>
                        <span class="text-foreground">No verás los informes</span> que reciba: solo el propietario los ve.
                    </li>
                    <li>
                        Mientras seas miembro <span class="text-foreground">no podrás reportar</span> a los programas de esta empresa (conflicto de
                        interés). Los informes que ya enviaste los seguirás viendo.
                    </li>
                    <li>Solo puedes pertenecer a una empresa, y puedes salir cuando el propietario te retire.</li>
                </ul>
                <div class="flex gap-2">
                    <Button disabled={enviando === invitacion.id || empresaActual !== null} onclick={() => aceptar(invitacion)} data-test="aceptar-invitacion">
                        Aceptar
                    </Button>
                    <Button variant="outline" disabled={enviando === invitacion.id} onclick={() => enviar(invitacion.id, 'rechazar')} data-test="rechazar-invitacion">
                        Rechazar
                    </Button>
                </div>
            </CardContent>
        </Card></div>
    {/each}

    {#if historial.length > 0}
        <Card>
            <CardHeader><CardTitle class="text-base">Anteriores</CardTitle></CardHeader>
            <CardContent class="divide-y text-sm">
                {#each historial as invitacion (invitacion.id)}
                    <div class="flex items-center justify-between gap-3 py-2" data-test="invitacion-anterior">
                        <span>{invitacion.empresa.nombre}</span>
                        <span class="text-muted-foreground">{estadoDe(invitacion)} · {fecha(invitacion.respondida_en ?? invitacion.expira_en)}</span>
                    </div>
                {/each}
            </CardContent>
        </Card>
    {/if}
</div>
