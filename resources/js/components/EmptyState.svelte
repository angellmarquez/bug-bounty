<script lang="ts">
    import type { Component } from 'svelte';
    import type { Snippet } from 'svelte';

    let {
        icon,
        title,
        description,
        children,
    }: {
        icon?: Component<{ class?: string }>;
        title: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border px-6 py-16 text-center"
>
    {#if icon}
        <div class="mb-4 rounded-full bg-muted p-4">
            <icon class="size-8 text-muted-foreground"></icon>
        </div>
    {/if}
    <h3 class="mb-1 text-lg font-semibold">{title}</h3>
    {#if description}
        <p class="mb-4 max-w-sm text-sm text-muted-foreground">{description}</p>
    {/if}
    {#if children}
        <div>{@render children?.()}</div>
    {/if}
</div>
