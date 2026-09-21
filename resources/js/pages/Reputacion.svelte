<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Mi reputación',
                href: '/reputacion',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Award from '@lucide/svelte/icons/award';
    import History from '@lucide/svelte/icons/history';
    import AppHead from '@/components/AppHead.svelte';
    import ApelacionesList from '@/components/ApelacionesList.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import ReputacionChart from '@/components/ReputacionChart.svelte';
    import SancionesList from '@/components/SancionesList.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Label } from '@/components/ui/label';
    import type { CuentaEstado } from '@/lib/rangos';
    import type { Apelacion, EntradaReputacion, Sancion } from '@/types/domain';

    type Paginado<T> = {
        data: T[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
    };

    let {
        seccion = 'resumen',
        saldo,
        historial = [],
        sanciones,
        apelaciones,
    }: {
        seccion?: 'resumen' | 'sanciones' | 'apelaciones';
        saldo: number;
        historial?: EntradaReputacion[];
        sanciones?: Paginado<Sancion>;
        apelaciones?: Paginado<Apelacion>;
    } = $props();

    const cuenta = $derived(page.props.cuenta as CuentaEstado | null | undefined);

    const PESTANAS = [
        { id: 'resumen', label: 'Resumen', href: '/reputacion' },
        { id: 'sanciones', label: 'Sanciones', href: '/reputacion/sanciones' },
        { id: 'apelaciones', label: 'Apelaciones', href: '/reputacion/apelaciones' },
    ] as const;

    const MOTIVOS: Record<string, string> = {
        reporte_validado: 'Reporte validado',
        reporte_resuelto: 'Informe resuelto',
        // Movimientos antiguos, de cuando la plataforma todavía registraba pagos.
        reporte_pagado: 'Informe pagado (histórico)',
        calidad_documentacion: 'Calidad de la documentación',
        participacion: 'Participación',
    };

    function etiquetaMotivo(motivo: string): string {
        return MOTIVOS[motivo] ?? motivo.replaceAll('_', ' ');
    }

    function formatearFecha(dateStr: string): string {
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(dateStr));
    }

    let apelando = $state<number | null>(null);
    let motivoApelacion = $state('');
    let enviando = $state(false);
    let errorApelacion = $state('');

    const dialogoAbierto = $derived(apelando !== null);

    function abrirApelacion(sancionId: number) {
        motivoApelacion = '';
        errorApelacion = '';
        apelando = sancionId;
    }

    function cerrarApelacion() {
        apelando = null;
    }

    function enviarApelacion(e: SubmitEvent) {
        e.preventDefault();
        if (apelando === null) return;
        if (!motivoApelacion.trim()) {
            errorApelacion = 'Explica por qué consideras que la sanción es injusta.';
            return;
        }

        enviando = true;
        router.post(
            `/reputacion/sanciones/${apelando}/apelar`,
            { motivo: motivoApelacion },
            {
                preserveScroll: true,
                onSuccess: cerrarApelacion,
                onError: (errores) => {
                    errorApelacion = errores.motivo ?? 'No se pudo enviar la apelación.';
                },
                onFinish: () => {
                    enviando = false;
                },
            },
        );
    }
</script>

<AppHead title="Mi reputación" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Mi reputación"
        description="Tu saldo, el historial de puntos y el estado de tus sanciones."
    />

    <nav class="flex gap-1 border-b border-border" aria-label="Secciones de reputación">
        {#each PESTANAS as pestana (pestana.id)}
            <Link
                href={pestana.href}
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors {seccion === pestana.id
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
                aria-current={seccion === pestana.id ? 'page' : undefined}
            >
                {pestana.label}
            </Link>
        {/each}
    </nav>

    {#if seccion === 'resumen'}
        <div class="grid gap-4 lg:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Saldo actual</CardDescription>
                    <CardTitle class="text-4xl font-bold">{saldo} <span class="text-base font-normal text-muted-foreground">pts</span></CardTitle>
                    <div class="pt-1"><RangoBadge puntos={saldo} /></div>
                </CardHeader>
                <CardContent class="space-y-2 text-sm text-muted-foreground">
                    {#if cuenta?.rango}
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuenow={cuenta.rango.progreso}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Avance hacia el siguiente rango"
                        >
                            <div class="h-full rounded-full bg-primary" style="width: {cuenta.rango.progreso}%"></div>
                        </div>
                        <p class="text-xs">
                            {#if cuenta.rango.siguiente}
                                Te faltan {cuenta.rango.faltan} pts para {cuenta.rango.siguiente.nombre}.
                            {:else}
                                Rango más alto alcanzado.
                            {/if}
                        </p>
                    {/if}
                    <p>Los reportes válidos suman puntos; las sanciones los restan.</p>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Evolución del saldo</CardTitle>
                </CardHeader>
                <CardContent>
                    {#if historial.length === 0}
                        <p class="py-12 text-center text-sm text-muted-foreground">
                            Aún no hay movimientos para graficar.
                        </p>
                    {:else}
                        <ReputacionChart {historial} />
                    {/if}
                </CardContent>
            </Card>
        </div>

        <h2 class="text-lg font-semibold">Historial de movimientos</h2>
        {#if historial.length === 0}
            <EmptyState
                icon={History}
                title="Todavía no tienes movimientos"
                description="Cuando un reporte tuyo sea validado o resuelto, verás aquí los puntos ganados."
            />
        {:else}
            <div class="flex flex-col gap-2">
                {#each historial as entrada (entrada.id)}
                    <Card>
                        <CardContent class="flex items-center justify-between gap-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-muted p-2">
                                    <Award class="size-4 text-muted-foreground" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium capitalize">
                                        {etiquetaMotivo(entrada.motivo)}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {formatearFecha(entrada.created_at)}
                                        {#if entrada.reporte}
                                            ·
                                            <Link
                                                href="/reportes/{entrada.reporte.id}"
                                                class="underline underline-offset-2 hover:text-foreground"
                                            >
                                                {entrada.reporte.numero_reporte}
                                            </Link>
                                        {/if}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-sm font-semibold {entrada.puntos >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-destructive'}"
                            >
                                {entrada.puntos > 0 ? '+' : ''}{entrada.puntos} pts
                            </span>
                        </CardContent>
                    </Card>
                {/each}
            </div>
        {/if}
    {:else if seccion === 'sanciones' && sanciones}
        <SancionesList {sanciones} showActions onApelar={abrirApelacion} />
    {:else if seccion === 'apelaciones' && apelaciones}
        <ApelacionesList {apelaciones} />
    {/if}
</div>

<Dialog
    open={dialogoAbierto}
    onOpenChange={(abierto) => {
        if (!abierto) cerrarApelacion();
    }}
>
    <DialogContent>
        <form onsubmit={enviarApelacion} class="space-y-4">
            <div>
                <DialogTitle>Apelar sanción</DialogTitle>
                <DialogDescription>
                    Explica por qué consideras que la sanción no corresponde. Otro moderador o el administrador revisará tu caso (nunca quien la aplicó) y podrás seguirlo en Apelaciones.
                </DialogDescription>
            </div>

            <div class="space-y-2">
                <Label for="motivo-apelacion">Motivo de la apelación</Label>
                <textarea
                    id="motivo-apelacion"
                    bind:value={motivoApelacion}
                    rows="5"
                    maxlength="2000"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    placeholder="Describe con detalle tu caso..."
                ></textarea>
                {#if errorApelacion}
                    <p class="text-sm text-destructive" role="alert">{errorApelacion}</p>
                {/if}
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onclick={cerrarApelacion}>Cancelar</Button>
                <Button type="submit" disabled={enviando}>
                    {enviando ? 'Enviando...' : 'Enviar apelación'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
