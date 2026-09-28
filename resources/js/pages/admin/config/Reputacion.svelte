<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin',
            },
            {
                title: 'Config Reputación',
                href: '/admin/config/reputacion',
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Settings from '@lucide/svelte/icons/settings';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { Severidad } from '@/types/enums';

    type PuntosEvento = { reporte_validado: number; reporte_resuelto: number };

    let {
        config,
    }: {
        config: {
            puntos_inicial: number;
            puntos: PuntosEvento;
            puntos_por_severidad: Record<Severidad, PuntosEvento>;
            penalizacion: { leve: number; media: number; grave: number };
            suspension: {
                leve: { dias: number };
                media: { dias: number };
                grave: { dias: number };
            };
            plazo_apelacion_dias: number;
        };
    } = $props();

    // De mayor a menor impacto, como se lee una tabla de recompensas.
    const SEVERIDADES: { valor: Severidad; nombre: string }[] = [
        { valor: 'critica', nombre: 'Crítica' },
        { valor: 'alta', nombre: 'Alta' },
        { valor: 'media', nombre: 'Media' },
        { valor: 'baja', nombre: 'Baja' },
        { valor: 'ninguna', nombre: 'Ninguna' },
    ];

    // Copia editable: no se sincroniza con las props al guardar (la página vuelve con lo guardado).
    let form = $state($state.snapshot(config));
    let errores = $state<Record<string, string>>({});
    let guardando = $state(false);

    function guardar(e: SubmitEvent) {
        e.preventDefault();
        guardando = true;
        errores = {};
        router.put(
            '/admin/config/reputacion',
            {
                puntos_inicial: form.puntos_inicial,
                puntos_por_severidad: form.puntos_por_severidad,
                puntos: {
                    reporte_validado: form.puntos.reporte_validado,
                    reporte_resuelto: form.puntos.reporte_resuelto,
                },
                penalizacion: form.penalizacion,
                suspension: form.suspension,
                plazo_apelacion_dias: form.plazo_apelacion_dias,
            },
            {
                preserveScroll: true,
                onError: (e) => (errores = e as Record<string, string>),
                onFinish: () => (guardando = false),
            },
        );
    }

    const hayErrores = $derived(Object.keys(errores).length > 0);
</script>

<AppHead title="Configuración de Reputación" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Configuración de Reputación"
        description="Ajusta los parámetros del sistema de reputación y penalización"
    />

    <form onsubmit={guardar} class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Settings class="h-5 w-5 text-primary" />
                    Puntos por severidad
                </CardTitle>
                <CardDescription>
                    Lo que gana el investigador según la severidad CVSS del informe: al confirmarlo la empresa y al
                    cerrarlo como resuelto. Se aplica a los informes que se confirmen o cierren desde ahora.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-4 font-medium">Severidad</th>
                                <th class="py-2 pr-4 font-medium">Confirmado por la empresa</th>
                                <th class="py-2 font-medium">Cerrado como resuelto</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each SEVERIDADES as severidad (severidad.valor)}
                                <tr class="border-b border-border/50 last:border-0">
                                    <td class="py-2 pr-4"><SeverityBadge severidad={severidad.valor} /></td>
                                    <td class="py-2 pr-4">
                                        <Input
                                            type="number"
                                            min="0"
                                            aria-label={`Puntos al confirmar un informe de severidad ${severidad.nombre}`}
                                            bind:value={form.puntos_por_severidad[severidad.valor].reporte_validado}
                                        />
                                        <InputError message={errores[`puntos_por_severidad.${severidad.valor}.reporte_validado`]} />
                                    </td>
                                    <td class="py-2">
                                        <Input
                                            type="number"
                                            min="0"
                                            aria-label={`Puntos al resolver un informe de severidad ${severidad.nombre}`}
                                            bind:value={form.puntos_por_severidad[severidad.valor].reporte_resuelto}
                                        />
                                        <InputError message={errores[`puntos_por_severidad.${severidad.valor}.reporte_resuelto`]} />
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Informes sin severidad</CardTitle>
                    <CardDescription>
                        Puntos para los informes que se cierran sin puntuación CVSS calculada.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="reporte_validado">Confirmado por la empresa</Label>
                            <Input id="reporte_validado" type="number" min="0" bind:value={form.puntos.reporte_validado} class="mt-1" />
                            <InputError message={errores['puntos.reporte_validado']} />
                        </div>
                        <div>
                            <Label for="reporte_resuelto">Cerrado como resuelto</Label>
                            <Input id="reporte_resuelto" type="number" min="0" bind:value={form.puntos.reporte_resuelto} class="mt-1" />
                            <InputError message={errores['puntos.reporte_resuelto']} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Puntos de bienvenida</CardTitle>
                    <CardDescription>
                        Puntos con los que empieza cada investigador que se registre desde ahora (0 = sin puntos).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="max-w-xs">
                        <Label for="puntos_inicial">Puntos iniciales</Label>
                        <Input id="puntos_inicial" type="number" min="0" bind:value={form.puntos_inicial} class="mt-1" />
                        <InputError message={errores.puntos_inicial} />
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Penalización base</CardTitle>
                <CardDescription>
                    Puntos que resta cada sanción (valores negativos o 0). Con sanciones previas vigentes se multiplican
                    por reincidencia.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label for="penalizacion_leve">Leve</Label>
                        <Input id="penalizacion_leve" type="number" max="0" bind:value={form.penalizacion.leve} class="mt-1" />
                        <InputError message={errores['penalizacion.leve']} />
                    </div>
                    <div>
                        <Label for="penalizacion_media">Media</Label>
                        <Input id="penalizacion_media" type="number" max="0" bind:value={form.penalizacion.media} class="mt-1" />
                        <InputError message={errores['penalizacion.media']} />
                    </div>
                    <div>
                        <Label for="penalizacion_grave">Grave</Label>
                        <Input id="penalizacion_grave" type="number" max="0" bind:value={form.penalizacion.grave} class="mt-1" />
                        <InputError message={errores['penalizacion.grave']} />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Suspensión</CardTitle>
                    <CardDescription>Días de suspensión según la gravedad de la sanción (0 = sin suspensión).</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label for="suspension_leve_dias">Leve (días)</Label>
                            <Input id="suspension_leve_dias" type="number" min="0" bind:value={form.suspension.leve.dias} class="mt-1" />
                            <InputError message={errores['suspension.leve.dias']} />
                        </div>
                        <div>
                            <Label for="suspension_media_dias">Media (días)</Label>
                            <Input id="suspension_media_dias" type="number" min="0" bind:value={form.suspension.media.dias} class="mt-1" />
                            <InputError message={errores['suspension.media.dias']} />
                        </div>
                        <div>
                            <Label for="suspension_grave_dias">Grave (días)</Label>
                            <Input id="suspension_grave_dias" type="number" min="0" bind:value={form.suspension.grave.dias} class="mt-1" />
                            <InputError message={errores['suspension.grave.dias']} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Apelaciones</CardTitle>
                    <CardDescription>Días que tiene el investigador para apelar una sanción.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="max-w-xs">
                        <Label for="plazo_apelacion_dias">Plazo (días)</Label>
                        <Input id="plazo_apelacion_dias" type="number" min="1" bind:value={form.plazo_apelacion_dias} class="mt-1" />
                        <InputError message={errores.plazo_apelacion_dias} />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-4">
            {#if hayErrores}
                <p role="alert" class="text-sm text-destructive">Revisa los campos marcados: no se guardó ningún cambio.</p>
            {/if}
            <Button type="submit" size="lg" disabled={guardando} data-test="guardar-config-reputacion">
                {#if guardando}<Spinner />{/if}
                {guardando ? 'Guardando…' : 'Guardar configuración'}
            </Button>
        </div>
    </form>
</div>
