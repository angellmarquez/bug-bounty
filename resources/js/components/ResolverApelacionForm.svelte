<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';

    let { apelacionId }: { apelacionId: number } = $props();

    let nota = $state('');
    let error = $state('');
    let enviando = $state(false);

    function resolver(aprobada: boolean) {
        if (!nota.trim()) {
            error = 'Escribe una nota explicando la decisión.';
            return;
        }
        error = '';
        enviando = true;
        router.post(
            `/moderacion/apelaciones/${apelacionId}/resolver`,
            { aprobada, nota: nota.trim() },
            {
                preserveScroll: true,
                onSuccess: () => (nota = ''),
                onError: (errores) => (error = errores.nota ?? 'No se pudo resolver la apelación.'),
                onFinish: () => (enviando = false),
            },
        );
    }
</script>

<div class="space-y-2 border-t border-border pt-3" data-test="resolver-form">
    <Input
        type="text"
        placeholder="Nota de resolución (obligatoria)..."
        bind:value={nota}
        aria-invalid={error ? 'true' : undefined}
    />
    {#if error}
        <p class="text-xs text-destructive" role="alert">{error}</p>
    {/if}
    <p class="text-[11px] text-muted-foreground">
        Tu decisión queda registrada con tu nombre, rol, fecha e IP.
    </p>
    <div class="flex gap-2">
        <Button
            size="sm"
            class="flex-1 bg-chart-1 text-white hover:bg-chart-1/90"
            disabled={enviando}
            onclick={() => resolver(true)}
            data-test="aprobar-apelacion"
        >
            Aprobar
        </Button>
        <Button
            variant="destructive"
            size="sm"
            class="flex-1"
            disabled={enviando}
            onclick={() => resolver(false)}
            data-test="rechazar-apelacion"
        >
            Rechazar
        </Button>
    </div>
</div>
