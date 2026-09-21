<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Ban from '@lucide/svelte/icons/ban';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import EstadoCuentaBadges from '@/components/EstadoCuentaBadges.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { estadoEmpresa, type CuentaEstado } from '@/lib/rangos';

    const cuenta = $derived(page.props.cuenta as CuentaEstado | null | undefined);
    const esInvestigador = $derived(cuenta?.roles.some((rol) => rol.slug === 'investigador') ?? false);

    function fecha(iso: string | null): string {
        if (!iso) return '';
        return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(iso));
    }
</script>

{#if cuenta}
    <div data-test="estado-cuenta">
    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="text-base">Mi estado</CardTitle>
            <CardDescription>Cómo participas hoy en la plataforma.</CardDescription>
            <EstadoCuentaBadges class="pt-1" />
        </CardHeader>
        <CardContent class="space-y-4 text-sm">
            {#if cuenta.suspension}
                <div class="flex gap-2 rounded-md border border-rose-500/40 bg-rose-500/10 p-3" role="alert">
                    <Ban class="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" aria-hidden="true" />
                    <div class="space-y-1">
                        <p class="font-medium">Cuenta suspendida hasta el {fecha(cuenta.suspension.hasta)}</p>
                        <p class="text-muted-foreground">
                            Hasta entonces no puedes enviar informes nuevos ({cuenta.suspension.motivo}). Puedes seguir usando la
                            plataforma y
                            <Link href="/reputacion/sanciones" class="underline underline-offset-2">apelar la sanción</Link>.
                        </p>
                    </div>
                </div>
            {/if}

            {#if esInvestigador}
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">Reputación</span>
                        <span class="flex items-center gap-2 font-semibold">
                            {cuenta.reputacion} pts <RangoBadge puntos={cuenta.reputacion} />
                        </span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={cuenta.rango.progreso}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label="Avance hacia el siguiente rango"
                    >
                        <div class="h-full rounded-full bg-primary transition-all" style="width: {cuenta.rango.progreso}%"></div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {#if cuenta.rango.siguiente}
                            Te faltan {cuenta.rango.faltan} pts para el rango {cuenta.rango.siguiente.nombre}.
                        {:else}
                            Has alcanzado el rango más alto.
                        {/if}
                    </p>
                </div>
            {/if}

            {#if cuenta.moderador}
                <div class="space-y-1">
                    <div class="flex items-center gap-2 font-medium">
                        <ShieldCheck class="size-4 text-violet-600 dark:text-violet-400" aria-hidden="true" />
                        Moderador
                    </div>
                    {#if cuenta.moderador.programas.length > 0}
                        <p class="text-muted-foreground">
                            Revisas los informes de:
                            {#each cuenta.moderador.programas as programa, i (programa.id)}
                                <Link href="/programas/{programa.id}" class="underline underline-offset-2">{programa.nombre}</Link>{i < cuenta.moderador.programas.length - 1 ? ', ' : ''}
                            {/each}.
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Para evitar conflictos de interés no puedes enviar informes a esos programas; en los demás puedes reportar con normalidad.
                        </p>
                    {:else}
                        <p class="text-muted-foreground">Todavía no tienes programas asignados; un administrador te asignará los que vas a moderar.</p>
                    {/if}
                </div>
            {/if}

            {#if cuenta.empresa}
                {@const estado = estadoEmpresa(cuenta.empresa.estado)}
                <div class="space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">Empresa</span>
                        <span class="font-medium">{cuenta.empresa.nombre}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">Estado de la solicitud</span>
                        <span class="font-medium">{estado.etiqueta}</span>
                    </div>
                    {#if cuenta.empresa.rol_interno}
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">Tu rol en la empresa</span>
                            <span class="font-medium capitalize">{cuenta.empresa.rol_interno}</span>
                        </div>
                    {/if}
                    {#if cuenta.empresa.rol_interno === 'publicador'}
                        <p class="text-xs text-muted-foreground" data-test="aviso-conflicto-empresa">
                            Para evitar conflictos de interés no puedes enviar informes a los programas de {cuenta.empresa.nombre} mientras seas
                            miembro; en los demás puedes reportar con normalidad y sigues viendo tus informes anteriores.
                        </p>
                    {/if}
                    {#if cuenta.empresa.motivo}
                        <p class="text-xs text-muted-foreground">Motivo: {cuenta.empresa.motivo}</p>
                    {/if}
                </div>
            {/if}
        </CardContent>
    </Card>
    </div>
{/if}
