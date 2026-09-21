<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
        groupLabel = 'Plataforma',
    }: {
        items: NavItem[];
        groupLabel?: string;
    } = $props();

    const url = currentUrlState();

    // Se marca la entrada cuya ruta es la más específica que contiene la página actual, para que
    // /reportes/12 resalte "Reportes" y /empresa/reportes no resalte también "/empresa".
    const rutaActiva = $derived.by(() => {
        const actual = url.currentUrl;
        const rutas = items
            .map((item) => String(toUrl(item.href)).split('?')[0])
            .filter((ruta) => actual === ruta || actual.startsWith(`${ruta}/`))
            .sort((a, b) => b.length - a.length);
        return rutas[0] ?? null;
    });
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (toUrl(item.href))}
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={String(toUrl(item.href)).split('?')[0] === rutaActiva}
                    tooltip={item.title}
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(item.href)}
                            class={props.class}
                        >
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
