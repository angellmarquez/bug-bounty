<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Ban from '@lucide/svelte/icons/ban';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import {
        estadoSancionColor,
        estadoSancionLabel,
        gravedadSancionColor,
        gravedadSancionLabel,
    } from '@/lib/status-colors';
    import type { SancionDashboard } from '@/types/domain';

    let { sancion }: { sancion: SancionDashboard | null } = $props();

    let motivo = $state('');
    let enviando = $state(false);
    let error = $state('');

    function formatearFecha(dateStr: string | null): string {
        if (!dateStr) return 'N/A';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr));
    }

    function enviarApelacion() {
        if (!sancion) return;
        const texto = motivo.trim();
        if (!texto) {
            error = 'Escribe los motivos de tu apelación.';
            return;
        }
        error = '';
        enviando = true;
        router.post(
            `/reputacion/sanciones/${sancion.id}/apelar`,
            { motivo: texto },
            {
                preserveScroll: true,
                onSuccess: () => {
                    motivo = '';
                },
                onError: (errores) => {
                    error = errores.motivo ?? 'No se pudo enviar la apelación.';
                },
                onFinish: () => {
                    enviando = false;
                },
            },
        );
    }
</script>

{#if sancion}
    <div class="w-full" data-test="sancion-alerta-dashboard">
        <Card class="border-destructive/40 bg-destructive/5">
            <CardHeader class="pb-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        {#if sancion.suspension_desde && sancion.suspension_hasta}
                            <Ban class="size-5 text-destructive" />
                            <CardTitle class="text-base text-destructive font-semibold">
                                Cuenta Suspendida por Sanción
                            </CardTitle>
                        {:else}
                            <ShieldAlert class="size-5 text-chart-4" />
                            <CardTitle class="text-base text-chart-4 font-semibold">
                                Aviso de Sanción Activa
                            </CardTitle>
                        {/if}
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class={gravedadSancionColor(sancion.gravedad)}>
                            {gravedadSancionLabel(sancion.gravedad)}
                        </span>
                        <span class={estadoSancionColor(sancion.estado)}>
                            {estadoSancionLabel(sancion.estado)}
                        </span>
                        <span class="rounded bg-destructive/20 px-2 py-0.5 text-xs font-semibold text-destructive">
                            {-Math.abs(sancion.puntos)} pts
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div class="space-y-1">
                    <p class="font-medium text-foreground">
                        Motivo: <span class="text-muted-foreground font-normal">{sancion.motivo}</span>
                    </p>
                    {#if sancion.reporte}
                        <p class="text-xs text-muted-foreground">
                            Informe asociado:
                            <Link href="/reportes/{sancion.reporte.id}" class="text-primary underline">
                                {sancion.reporte.numero_reporte} · {sancion.reporte.titulo}
                            </Link>
                        </p>
                    {/if}
                    {#if sancion.moderador}
                        <p class="text-xs text-muted-foreground">
                            Aplicada por el moderador: <span class="font-medium text-foreground">{sancion.moderador.name}</span>
                        </p>
                    {/if}
                </div>

                {#if sancion.comentario_moderador}
                    <div class="rounded-md border border-border bg-background/60 p-3">
                        <p class="text-xs font-semibold text-foreground">
                            Comentario del moderador ({sancion.moderador?.name ?? 'Moderación'}):
                        </p>
                        <p class="mt-1 text-xs italic text-muted-foreground whitespace-pre-wrap">
                            "{sancion.comentario_moderador}"
                        </p>
                    </div>
                {/if}

                {#if sancion.suspension_desde && sancion.suspension_hasta}
                    <div class="rounded-md border border-destructive/30 bg-destructive/10 p-2.5 text-xs text-destructive">
                        Período de suspensión activa: desde el {formatearFecha(sancion.suspension_desde)} hasta el {formatearFecha(sancion.suspension_hasta)}. Durante este período no puedes enviar nuevos reportes.
                    </div>
                {/if}

                {#if sancion.puede_apelar}
                    <div class="space-y-2 border-t border-border pt-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-foreground">
                                Apelar esta sanción ante el Administrador
                            </h4>
                            {#if sancion.plazo_apelacion}
                                <span class="text-[11px] text-muted-foreground">
                                    Plazo límite: {formatearFecha(sancion.plazo_apelacion)}
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Si consideras que la sanción es injusta o posees justificaciones técnicas sobre tu reporte, explica tu caso. El Administrador revisará tus argumentos para determinar si se levanta la sanción y se devuelven los puntos.
                        </p>
                        <textarea
                            bind:value={motivo}
                            placeholder="Escribe tus argumentos o aclaraciones para que el Administrador revise tu caso..."
                            rows={3}
                            class="w-full rounded-md border border-input bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                        {#if error}
                            <p class="text-xs text-destructive">{error}</p>
                        {/if}
                        <div class="flex justify-end">
                            <Button
                                size="sm"
                                disabled={enviando || !motivo.trim()}
                                onclick={enviarApelacion}
                                class="bg-primary text-primary-foreground hover:bg-primary/90"
                            >
                                {enviando ? 'Enviando apelación...' : 'Enviar apelación al Administrador'}
                            </Button>
                        </div>
                    </div>
                {:else if sancion.estado === 'apelada'}
                    <div class="rounded-md border border-chart-4/30 bg-chart-4/10 p-3 space-y-1.5">
                        <div class="flex items-center gap-2 font-medium text-xs text-chart-4">
                            <MessageSquare class="size-4" />
                            <span>Apelación en curso de revisión por el Administrador</span>
                        </div>
                        {#if sancion.apelacion?.motivo}
                            <p class="text-xs text-muted-foreground italic">
                                Justificación presentada: "{sancion.apelacion.motivo}"
                            </p>
                        {/if}
                        <div class="pt-1">
                            <Link href="/reputacion/apelaciones" class="text-xs text-primary hover:underline">
                                Ver seguimiento completo en Mis Apelaciones &rarr;
                            </Link>
                        </div>
                    </div>
                {:else if !sancion.en_plazo}
                    <p class="text-xs text-muted-foreground border-t border-border pt-2">
                        El plazo para apelar ({formatearFecha(sancion.plazo_apelacion)}) ha vencido.
                    </p>
                {/if}
            </CardContent>
        </Card>
    </div>
{/if}
