<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import CvssCalculator from '@/components/CvssCalculator.svelte';

    let {
        open = $bindable(false),
        reporteId,
        vectorActual = null,
        puntuacionActual = null,
        onsuccess,
    }: {
        open: boolean;
        reporteId: number;
        vectorActual?: string | null;
        puntuacionActual?: number | string | null;
        onsuccess?: () => void;
    } = $props();

    // El moderador parte del vector del investigador y lo corrige; el servidor recalcula la nota.
    // svelte-ignore state_referenced_locally
    let vector = $state(vectorActual ?? '');
    let nota = $state('');
    let processing = $state(false);

    const errores = $derived(page.props.errors ?? {});
    const sinCambios = $derived(vector === (vectorActual ?? ''));

    function submit() {
        processing = true;
        router.post(`/reportes/${reporteId}/cvss`, { vector_cvss: vector, nota }, {
            preserveScroll: true,
            onSuccess: () => {
                open = false;
                nota = '';
                onsuccess?.();
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
        <DialogTitle>Ajustar CVSS</DialogTitle>
        <DialogDescription>
            Corrige las métricas si el investigador sobrevaloró o infravaloró el impacto.
            Actual: {puntuacionActual ?? 'sin CVSS'}. La puntuación final la calcula el servidor y el cambio queda en la línea de tiempo.
        </DialogDescription>

        <div class="space-y-4" data-test="ajustar-cvss">
            <CvssCalculator vector={vectorActual ?? ''} onChange={(v) => (vector = v)} />
            {#if errores.vector_cvss}
                <p class="text-sm text-destructive">{errores.vector_cvss}</p>
            {/if}

            <div class="space-y-2">
                <Label for="nota-cvss">Motivo del ajuste (requerido)</Label>
                <textarea
                    id="nota-cvss"
                    bind:value={nota}
                    maxlength="2000"
                    placeholder="Ej.: la explotación requiere una sesión autenticada, por eso PR pasa a Bajo."
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                ></textarea>
                {#if errores.nota}
                    <p class="text-sm text-destructive">{errores.nota}</p>
                {/if}
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => (open = false)}>Cancelar</Button>
            <Button onclick={submit} disabled={processing || sinCambios || !nota.trim()}>
                {processing ? 'Guardando...' : 'Guardar ajuste'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
