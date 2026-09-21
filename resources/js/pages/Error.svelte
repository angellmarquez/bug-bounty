<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Component } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import Ban from '@lucide/svelte/icons/ban';
    import ClockAlert from '@lucide/svelte/icons/clock-alert';
    import FileQuestion from '@lucide/svelte/icons/file-search';
    import Hammer from '@lucide/svelte/icons/hammer';
    import Home from '@lucide/svelte/icons/house';
    import MousePointerBan from '@lucide/svelte/icons/mouse-pointer-ban';
    import RotateCw from '@lucide/svelte/icons/rotate-cw';
    import ServerCrash from '@lucide/svelte/icons/server-crash';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';

    let {
        status,
        mensaje = null,
        reintentar_en = null,
    }: {
        status: number;
        mensaje?: string | null;
        reintentar_en?: number | null;
    } = $props();

    type Descripcion = { titulo: string; texto: string; icono: Component<{ class?: string }>; reintentar: boolean };

    const CATALOGO: Record<number, Descripcion> = {
        403: {
            titulo: 'No tienes permiso para ver esto',
            texto: 'Tu cuenta no tiene acceso a esta página o a esta acción. Si crees que es un error, contacta a un administrador.',
            icono: ShieldAlert,
            reintentar: false,
        },
        404: {
            titulo: 'Página no encontrada',
            texto: 'La dirección no existe o el contenido ya no está disponible. Revisa el enlace o vuelve al inicio.',
            icono: FileQuestion,
            reintentar: false,
        },
        405: {
            titulo: 'Acción no permitida',
            texto: 'Esta dirección no admite esa acción. Usa los botones de la aplicación para navegar.',
            icono: MousePointerBan,
            reintentar: false,
        },
        409: {
            titulo: 'No se pudo completar la acción',
            texto: 'El estado actual no lo permite. Actualiza la página e inténtalo de nuevo.',
            icono: Ban,
            reintentar: false,
        },
        410: {
            titulo: 'Este contenido ya no está disponible',
            texto: 'El enlace o el contenido que buscas fue retirado.',
            icono: Ban,
            reintentar: false,
        },
        422: {
            titulo: 'No se pudo completar la acción',
            texto: 'Los datos enviados no se pueden procesar en el estado actual.',
            icono: Ban,
            reintentar: false,
        },
        429: {
            titulo: 'Demasiadas solicitudes',
            texto: 'Hiciste muchas acciones seguidas. Espera un momento antes de volver a intentarlo.',
            icono: ClockAlert,
            reintentar: true,
        },
        500: {
            titulo: 'Algo salió mal',
            texto: 'Tuvimos un problema de nuestra parte. Ya quedó registrado; inténtalo de nuevo en unos minutos.',
            icono: ServerCrash,
            reintentar: true,
        },
        503: {
            titulo: 'Estamos en mantenimiento',
            texto: 'La plataforma volverá en unos minutos. Gracias por tu paciencia.',
            icono: Hammer,
            reintentar: true,
        },
    };

    const info = $derived(CATALOGO[status] ?? CATALOGO[500]);
    const usuario = $derived(page.props.auth?.user ?? null);
    const inicio = $derived(usuario ? '/dashboard' : '/');
    const etiquetaInicio = $derived(usuario ? 'Ir al panel' : 'Ir al inicio');

    function volver() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = inicio;
        }
    }

    function reintentar() {
        window.location.reload();
    }
</script>

<AppHead title={`${status} · ${info.titulo}`} />

{#snippet contenido()}
    <div
        class="flex flex-1 items-center justify-center px-4 py-12"
        data-test="pagina-error"
        data-status={status}
    >
        <div class="flex w-full max-w-lg flex-col items-center text-center">
            <p class="text-7xl font-bold tracking-tight text-muted-foreground/30 select-none" aria-hidden="true">
                {status}
            </p>
            <div class="-mt-2 mb-5 rounded-full bg-muted p-4">
                <info.icono class="size-8 text-muted-foreground" />
            </div>
            <h1 class="mb-2 text-2xl font-semibold tracking-tight" data-test="error-titulo">{info.titulo}</h1>
            <p class="mb-2 max-w-md text-sm text-muted-foreground" data-test="error-texto">{info.texto}</p>
            {#if mensaje}
                <p
                    class="mt-2 max-w-md rounded-md border bg-muted/50 px-3 py-2 text-sm"
                    data-test="error-mensaje"
                >
                    {mensaje}
                </p>
            {/if}
            {#if status === 429 && reintentar_en}
                <p class="mt-2 text-xs text-muted-foreground" data-test="error-espera">
                    Puedes volver a intentarlo en unos {reintentar_en} segundos.
                </p>
            {/if}

            <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                <Button variant="outline" onclick={volver} data-test="error-volver">
                    <ArrowLeft class="h-4 w-4" />
                    Volver
                </Button>
                {#if info.reintentar}
                    <Button variant="outline" onclick={reintentar} data-test="error-reintentar">
                        <RotateCw class="h-4 w-4" />
                        Reintentar
                    </Button>
                {/if}
                <Button href={inicio} data-test="error-inicio">
                    <Home class="h-4 w-4" />
                    {etiquetaInicio}
                </Button>
            </div>
        </div>
    </div>
{/snippet}

{#if usuario}
    <AppLayout breadcrumbs={[{ title: 'Error' }]}>
        {@render contenido()}
    </AppLayout>
{:else}
    <div class="flex min-h-screen flex-col bg-background">
        {@render contenido()}
    </div>
{/if}
