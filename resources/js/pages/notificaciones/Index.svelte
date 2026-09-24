<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Notificaciones', href: '/notificaciones' }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Bell from '@lucide/svelte/icons/bell';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
    import { estiloDeAviso, haceTiempo } from '@/lib/notificaciones';
    import type { Notificacion } from '@/types/domain';
    import { etiquetaPaginacion } from '@/lib/paginacion';

    let {
        avisos,
        filtro,
    }: {
        avisos: {
            data: Notificacion[];
            links: { url: string | null; label: string; active: boolean }[];
            last_page: number;
            total: number;
        };
        filtro: 'todas' | 'no_leidas';
    } = $props();

    const sinLeer = $derived(avisos.data.filter((aviso) => !aviso.leida).length);

    function cambiarFiltro(nuevo: 'todas' | 'no_leidas') {
        router.get('/notificaciones', nuevo === 'no_leidas' ? { filtro: nuevo } : {}, { preserveState: true, replace: true });
    }

    function marcarLeida(id: string) {
        router.post(`/notificaciones/${id}/leer`, {}, { preserveScroll: true });
    }

    function marcarTodas() {
        router.post('/notificaciones/leer-todas', {}, { preserveScroll: true });
    }
</script>

<AppHead title="Notificaciones" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <PageHeader
            title="Notificaciones"
            description="Avisos de tus informes, sanciones, apelaciones y de la plataforma."
        />
        <Button variant="outline" size="sm" onclick={marcarTodas} data-test="marcar-todas-pagina">
            Marcar todas como leídas
        </Button>
    </div>

    <div class="flex gap-2" role="tablist" aria-label="Filtro de notificaciones">
        <Button
            variant={filtro === 'todas' ? 'default' : 'outline'}
            size="sm"
            role="tab"
            aria-selected={filtro === 'todas'}
            onclick={() => cambiarFiltro('todas')}
        >
            Todas
        </Button>
        <Button
            variant={filtro === 'no_leidas' ? 'default' : 'outline'}
            size="sm"
            role="tab"
            aria-selected={filtro === 'no_leidas'}
            onclick={() => cambiarFiltro('no_leidas')}
            data-test="filtro-no-leidas"
        >
            Sin leer
        </Button>
    </div>

    {#if avisos.data.length === 0}
        <EmptyState
            icon={Bell}
            title={filtro === 'no_leidas' ? 'No tienes avisos sin leer' : 'Aún no tienes notificaciones'}
            description="Cuando pase algo que te afecte (un informe, una sanción, una invitación) lo verás aquí."
        />
    {:else}
        <Card>
            <CardContent class="p-0">
                <ul class="divide-y" data-test="lista-avisos">
                    {#each avisos.data as aviso (aviso.id)}
                        {@const estilo = estiloDeAviso(aviso.tipo)}
                        <li class="flex items-start gap-3 px-4 py-3 {aviso.leida ? 'opacity-70' : 'bg-primary/5'}" data-test="aviso" data-leida={aviso.leida}>
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {estilo.clase}">
                                <estilo.icono class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <Link href="/notificaciones/{aviso.id}/abrir" class="block hover:underline">
                                    <span class="text-sm font-medium">{aviso.titulo}</span>
                                </Link>
                                <p class="text-sm text-muted-foreground">{aviso.mensaje}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{haceTiempo(aviso.created_at)}</p>
                            </div>
                            {#if !aviso.leida}
                                <Button variant="ghost" size="sm" onclick={() => marcarLeida(aviso.id)}>
                                    Marcar leída
                                </Button>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </CardContent>
        </Card>

        {#if avisos.last_page > 1}
            <nav class="flex items-center justify-center gap-1">
                {#each avisos.links as link (link.label)}
                    {#if link.url}
                        <a
                            href={link.url}
                            class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors hover:bg-secondary {link.active ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground'}"
                        >
                            {etiquetaPaginacion(link.label)}
                        </a>
                    {:else}
                        <span class="inline-flex h-9 items-center justify-center px-3 text-sm text-muted-foreground">
                            {etiquetaPaginacion(link.label)}
                        </span>
                    {/if}
                {/each}
            </nav>
        {/if}
    {/if}

    <p class="text-xs text-muted-foreground">{sinLeer} sin leer en esta página · {avisos.total} en total</p>
</div>
