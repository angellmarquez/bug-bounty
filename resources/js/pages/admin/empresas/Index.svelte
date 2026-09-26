<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Admin' },
            { title: 'Empresas', href: '/admin/empresas' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import type { Empresa } from '@/types/domain';

    let {
        empresas: empresasData,
        filtros,
    }: {
        empresas: {
            data: Empresa[];
            total: number;
            links: { url: string | null; label: string; active: boolean }[];
        };
        filtros: { estado?: string; busqueda?: string };
    } = $props();

    let busqueda = $state(filtros.busqueda ?? '');

    function buscar() {
        router.get('/admin/empresas', {
            ...(busqueda ? { busqueda } : {}),
            ...(filtros.estado ? { estado: filtros.estado } : {}),
        }, { preserveState: true, replace: true });
    }

    function cambiarEstado(empresaId: number, accion: 'aprobar' | 'rechazar' | 'suspender') {
        const motivo = accion === 'aprobar' ? 'Solicitud revisada por administración.' : 'Revisión administrativa pendiente de aclaraciones.';
        router.post(`/admin/empresas/${empresaId}/${accion}`, { motivo }, { preserveState: true });
    }

    function estadoLabel(estado: Empresa['estado']): string {
        return { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada', suspendida: 'Suspendida' }[estado];
    }
</script>

<AppHead title="Empresas" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
    <PageHeader title="Empresas" description={`${empresasData.total} empresa${empresasData.total === 1 ? '' : 's'} registrada${empresasData.total === 1 ? '' : 's'}`} />

    <Card>
        <CardContent>
            <div class="relative max-w-xl">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input bind:value={busqueda} onkeydown={(event) => event.key === 'Enter' && buscar()} placeholder="Buscar por empresa o identificador fiscal" class="pl-9" />
            </div>
        </CardContent>
    </Card>

    {#if empresasData.data.length === 0}
        <EmptyState icon={Building2} title="No hay empresas" description="No se encontraron empresas con los filtros actuales." />
    {:else}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {#each empresasData.data as empresa (empresa.id)}
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-3">
                            <CardTitle class="text-base">{empresa.nombre_comercial ?? empresa.razon_social}</CardTitle>
                            <span class="rounded-md bg-secondary px-2 py-1 text-xs font-medium">{estadoLabel(empresa.estado)}</span>
                        </div>
                        <p class="text-sm text-muted-foreground">{empresa.identificador_fiscal}</p>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">{empresa.email}</p>
                        {#if empresa.motivo_estado}<p class="text-xs text-muted-foreground">{empresa.motivo_estado}</p>{/if}
                        <div class="flex flex-wrap gap-2">
                            {#if empresa.estado === 'pendiente'}
                                <Button size="sm" onclick={() => cambiarEstado(empresa.id, 'aprobar')}>Aprobar</Button>
                                <Button size="sm" variant="destructive" onclick={() => cambiarEstado(empresa.id, 'rechazar')}>Rechazar</Button>
                            {:else if empresa.estado === 'aprobada'}
                                <Button size="sm" variant="destructive" onclick={() => cambiarEstado(empresa.id, 'suspender')}>Suspender</Button>
                            {:else if empresa.estado === 'suspendida'}
                                <Button size="sm" onclick={() => router.post(`/admin/empresas/${empresa.id}/reactivar`, {}, { preserveState: true })}>Reactivar</Button>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/each}
        </div>
    {/if}
</div>