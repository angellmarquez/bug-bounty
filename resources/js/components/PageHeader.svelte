<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        title,
        description,
        children,
    }: {
        title: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div class="flex items-center justify-between">
    <div class="space-y-1">
        <h1 class="text-2xl font-bold tracking-tight">{title}</h1>
        {#if description}
            <p class="text-sm text-muted-foreground">{description}</p>
        {/if}
    </div>
    {#if children}
        <div class="flex items-center gap-2">
            {@render children?.()}
        </div>
    {/if}
</div>
