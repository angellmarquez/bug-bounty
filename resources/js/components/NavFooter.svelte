<script lang="ts">
    import {
        SidebarGroup,
        SidebarGroupContent,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
        class: className = '',
    }: {
        items: NavItem[];
        class?: string;
    } = $props();
</script>

<SidebarGroup class={`group-data-[collapsible=icon]:p-0 ${className}`}>
    <SidebarGroupContent>
        <SidebarMenu>
            {#each items as item (toUrl(item.href))}
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="text-muted-foreground hover:text-foreground"
                        asChild
                    >
                        {#snippet children(props)}
                            <a
                                {...props}
                                href={toUrl(item.href)}
                                target="_blank"
                                rel="noopener noreferrer"
                                class={props.class}
                            >
                                {#if item.icon}
                                    <item.icon class="size-4 shrink-0" />
                                {/if}
                                <span>{item.title}</span>
                            </a>
                        {/snippet}
                    </SidebarMenuButton>
                </SidebarMenuItem>
            {/each}
        </SidebarMenu>
    </SidebarGroupContent>
</SidebarGroup>
