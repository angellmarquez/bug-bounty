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
    import PageHeader from '@/components/PageHeader.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';

    let {
        config,
    }: {
        config: {
            puntos_inicial: number;
            puntos: {
                reporte_validado: number;
                reporte_resuelto: number;
                calidad_documentacion: number;
                participacion: number;
            };
            penalizacion: {
                leve: number;
                media: number;
                grave: number;
            };
            suspension: {
                leve: { dias: number };
                media: { dias: number };
                grave: { dias: number };
            };
            plazo_apelacion_dias: number;
        };
    } = $props();

    let form = $state({
        puntos_iniciales: config.puntos_inicial,
        reporte_validado: config.puntos.reporte_validado,
        reporte_resuelto: config.puntos.reporte_resuelto,
        calidad_documentacion: config.puntos.calidad_documentacion,
        participacion: config.puntos.participacion,
        penalizacion_leve: config.penalizacion.leve,
        penalizacion_media: config.penalizacion.media,
        penalizacion_grave: config.penalizacion.grave,
        suspension_leve_dias: config.suspension.leve.dias,
        suspension_media_dias: config.suspension.media.dias,
        suspension_grave_dias: config.suspension.grave.dias,
        plazo_apelacion_dias: config.plazo_apelacion_dias,
    });

    function guardar(e: SubmitEvent) {
        e.preventDefault();
        router.put('/admin/config/reputacion', {
            puntos_inicial: form.puntos_iniciales,
            puntos: {
                reporte_validado: form.reporte_validado,
                reporte_resuelto: form.reporte_resuelto,
                calidad_documentacion: form.calidad_documentacion,
                participacion: form.participacion,
            },
            penalizacion: {
                leve: form.penalizacion_leve,
                media: form.penalizacion_media,
                grave: form.penalizacion_grave,
            },
            suspension: {
                leve: { dias: form.suspension_leve_dias },
                media: { dias: form.suspension_media_dias },
                grave: { dias: form.suspension_grave_dias },
            },
            plazo_apelacion_dias: form.plazo_apelacion_dias,
        }, {
            preserveState: true,
        });
    }
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
                    Puntos Iniciales
                </CardTitle>
                <CardDescription>
                    Puntos con los que inicia cada investigador nuevo
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="max-w-xs">
                    <Label for="puntos_iniciales">Puntos iniciales</Label>
                    <Input
                        id="puntos_iniciales"
                        type="number"
                        bind:value={form.puntos_iniciales}
                        min="0"
                        class="mt-1"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Puntos Positivos</CardTitle>
                <CardDescription>
                    Recompensa en puntos por acciones válidas
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <Label for="reporte_validado">Reporte confirmado por la empresa</Label>
                        <Input
                            id="reporte_validado"
                            type="number"
                            bind:value={form.reporte_validado}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="reporte_resuelto">Informe resuelto</Label>
                        <Input
                            id="reporte_resuelto"
                            type="number"
                            bind:value={form.reporte_resuelto}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="calidad_documentacion">Calidad documentación</Label>
                        <Input
                            id="calidad_documentacion"
                            type="number"
                            bind:value={form.calidad_documentacion}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="participacion">Participación</Label>
                        <Input
                            id="participacion"
                            type="number"
                            bind:value={form.participacion}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Penalización Base</CardTitle>
                <CardDescription>
                    Puntos negativos aplicados por cada sanción
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label for="penalizacion_leve">Leve</Label>
                        <Input
                            id="penalizacion_leve"
                            type="number"
                            bind:value={form.penalizacion_leve}
                            max="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="penalizacion_media">Media</Label>
                        <Input
                            id="penalizacion_media"
                            type="number"
                            bind:value={form.penalizacion_media}
                            max="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="penalizacion_grave">Grave</Label>
                        <Input
                            id="penalizacion_grave"
                            type="number"
                            bind:value={form.penalizacion_grave}
                            max="0"
                            class="mt-1"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Suspensión</CardTitle>
                <CardDescription>
                    Días de suspensión por gravedad de sanción
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label for="suspension_leve_dias">Leve (días)</Label>
                        <Input
                            id="suspension_leve_dias"
                            type="number"
                            bind:value={form.suspension_leve_dias}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="suspension_media_dias">Media (días)</Label>
                        <Input
                            id="suspension_media_dias"
                            type="number"
                            bind:value={form.suspension_media_dias}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="suspension_grave_dias">Grave (días)</Label>
                        <Input
                            id="suspension_grave_dias"
                            type="number"
                            bind:value={form.suspension_grave_dias}
                            min="0"
                            class="mt-1"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Apelaciones</CardTitle>
                <CardDescription>
                    Tiempo límite para presentar una apelación
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="max-w-xs">
                    <Label for="plazo_apelacion_dias">Plazo apelación (días)</Label>
                    <Input
                        id="plazo_apelacion_dias"
                        type="number"
                        bind:value={form.plazo_apelacion_dias}
                        min="1"
                        class="mt-1"
                    />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button type="submit" size="lg">
                Guardar configuración
            </Button>
        </div>
    </form>
</div>
