<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import Send from '@lucide/svelte/icons/send';

    let {
        reporteId,
        onsuccess,
    }: {
        reporteId: number;
        onsuccess?: () => void;
    } = $props();

    let nota = $state('');
    let processing = $state(false);

    function handleSubmit(e: Event) {
        e.preventDefault();
        if (!nota.trim()) return;

        processing = true;
        router.post(`/reportes/${reporteId}/comentar`, { nota }, {
            preserveScroll: true,
            onFinish: () => {
                processing = false;
                nota = '';
                onsuccess?.();
            },
        });
    }
</script>

<form onsubmit={handleSubmit} class="flex gap-2">
    <input
        type="text"
        bind:value={nota}
        placeholder="Escribe un comentario..."
        class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        disabled={processing}
    />
    <Button type="submit" size="icon" variant="outline" disabled={processing || !nota.trim()}>
        <Send class="h-4 w-4" />
    </Button>
</form>
