<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import { cn } from '@/lib/utils';
    import { ORDEN_ROLES, ROLES } from '@/lib/roles';

    let {
        descripciones = false,
        class: className = '',
    }: {
        /** Muestra debajo de cada rol qué puede hacer. */
        descripciones?: boolean;
        class?: string;
    } = $props();

    const roles = $derived(
        ORDEN_ROLES.filter((rol) => ((page.props.userRoles as string[] | undefined) ?? []).includes(rol)),
    );
</script>

{#if roles.length > 0}
    {#if descripciones}
        <ul class={cn('space-y-3', className)} data-test="roles-usuario">
            {#each roles as rol (rol)}
                <li class="space-y-1">
                    <span
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {ROLES[rol].clase}"
                        data-rol={rol}
                    >
                        {#if rol === 'moderador'}<ShieldCheck class="h-3 w-3" />{/if}
                        {ROLES[rol].etiqueta}
                    </span>
                    <p class="text-sm text-muted-foreground">{ROLES[rol].descripcion}</p>
                </li>
            {/each}
        </ul>
    {:else}
        <div class={cn('flex flex-wrap items-center gap-2', className)} data-test="roles-usuario">
            {#each roles as rol (rol)}
                <span
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {ROLES[rol].clase}"
                    data-rol={rol}
                >
                    {#if rol === 'moderador'}<ShieldCheck class="h-3 w-3" />{/if}
                    {ROLES[rol].etiqueta}
                </span>
            {/each}
        </div>
    {/if}
{/if}
