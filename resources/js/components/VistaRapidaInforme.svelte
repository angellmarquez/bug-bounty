<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import ContenidoInforme from '@/components/ContenidoInforme.svelte';
    import { Button } from '@/components/ui/button';
    import type { PocSchemaField } from '@/types/domain';

    type Contenido = {
        descripcion: string | null;
        poc: Record<string, unknown> | null;
        poc_schema: PocSchemaField[] | null;
        cifrado_indisponible: boolean;
        categoria: string | null;
        vector_cvss: string | null;
        puntuacion_cvss: number | string | null;
        puede_revisar: boolean;
    };

    let { reporteId }: { reporteId: number } = $props();

    let contenido = $state<Contenido | null>(null);
    let error = $state<string | null>(null);

    onMount(async () => {
        try {
            const respuesta = await fetch(`/reportes/${reporteId}/vista-rapida`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!respuesta.ok) throw new Error(String(respuesta.status));
            contenido = (await respuesta.json()) as Contenido;
        } catch {
            error = 'No se pudo cargar el informe. Ábrelo completo para verlo.';
        }
    });

    function iniciarRevision() {
        router.post(`/reportes/${reporteId}/revisar`, {}, { preserveScroll: true });
    }
</script>

<div class="space-y-4">
    {#if error}
        <p role="alert" class="text-sm text-destructive">{error}</p>
    {:else if contenido === null}
        <p class="text-sm text-muted-foreground">Cargando informe...</p>
    {:else}
        <ContenidoInforme
            descripcion={contenido.descripcion}
            poc={contenido.poc}
            pocSchema={contenido.poc_schema}
            categoria={contenido.categoria}
            vectorCvss={contenido.vector_cvss}
            puntuacionCvss={contenido.puntuacion_cvss}
            cifradoIndisponible={contenido.cifrado_indisponible}
        />
    {/if}

    <div class="flex flex-wrap gap-2">
        {#if contenido?.puede_revisar}
            <Button size="sm" onclick={iniciarRevision}>Iniciar revisión</Button>
        {/if}
        <Button size="sm" variant="outline" href={`/reportes/${reporteId}`}>
            Abrir informe completo (validar, rechazar, duplicado)
        </Button>
    </div>
</div>
