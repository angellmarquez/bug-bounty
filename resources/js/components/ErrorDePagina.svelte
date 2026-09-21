<script lang="ts">
    import AlertTriangle from '@lucide/svelte/icons/alert-triangle';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

    let {
        error,
        reset,
    }: {
        error: unknown;
        reset: () => void;
    } = $props();

    const detalle = $derived(error instanceof Error ? error.message : String(error));
</script>

<div class="flex flex-1 items-start justify-center p-4" data-test="error-de-pagina">
    <Card class="w-full max-w-xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <AlertTriangle class="h-5 w-5 text-destructive" />
                Esta página tuvo un problema
            </CardTitle>
            <CardDescription>
                El resto de la aplicación sigue funcionando: puedes usar el menú, reintentar o volver al inicio.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            {#if import.meta.env.DEV}
                <p class="break-words rounded-md bg-muted p-3 font-mono text-xs">{detalle}</p>
            {/if}
            <div class="flex flex-wrap gap-2">
                <Button onclick={reset}>Reintentar</Button>
                <Button variant="outline" href="/dashboard">Ir al Dashboard</Button>
            </div>
        </CardContent>
    </Card>
</div>
