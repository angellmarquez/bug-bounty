<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import FolderGit2 from '@lucide/svelte/icons/folder-git-2';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Bug from '@lucide/svelte/icons/bug';
    import Shield from '@lucide/svelte/icons/shield';
    import Award from '@lucide/svelte/icons/award';
    import Key from '@lucide/svelte/icons/key';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import Settings from '@lucide/svelte/icons/settings';
    import Users from '@lucide/svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as reportesIndex } from '@/routes/reportes';
    import { index as programasIndex, gestion as gestionProgramas } from '@/routes/programas';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const userRoles = $derived(
        (page.props.userRoles as string[]) ?? (page.props.auth?.user?.roles as string[]) ?? [],
    );

    const isAdmin = $derived(userRoles.includes('administrador'));
    const isGestion = $derived(userRoles.includes('gestion'));
    const isInvestigador = $derived(userRoles.includes('investigador'));
    const isEmpresa = $derived(userRoles.includes('empresa'));
    const isModerador = $derived(userRoles.includes('moderador'));

    const mainNavItems = $derived.by(() => {
        const items: NavItem[] = [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ];

        if (isModerador || isAdmin) {
            items.push({
                title: 'Moderación',
                href: '/moderacion',
                icon: ClipboardCheck,
            });
        }

        if (isInvestigador || isGestion || isAdmin || isEmpresa || isModerador) {
            items.push({
                title: isEmpresa
                    ? 'Reportes recibidos'
                    : isModerador || isAdmin
                      ? 'Todos los reportes'
                      : 'Mis Reportes',
                href: isEmpresa ? '/empresa/reportes' : reportesIndex(),
                icon: Bug,
            });
        }

        if (isInvestigador || isGestion || isAdmin) {
            items.push({
                title: 'Programas',
                href: programasIndex(),
                icon: Shield,
            });
        }

        /*
        if (isInvestigador || isGestion || isAdmin) {
            items.push({
                title: 'Mis Claves PGP',
                href: '/claves-pgp',
                icon: Key,
            });
        }
        */

        if (isInvestigador) {
            items.push({
                title: 'Mi reputación',
                href: '/reputacion',
                icon: Award,
            });
        }

        if (isGestion || isAdmin) {
            items.push({
                title: 'Gestión Programas',
                href: gestionProgramas(),
                icon: Shield,
            });
        }

        if (isEmpresa) {
            items.push({
                title: 'Panel empresa',
                href: '/empresa',
                icon: Shield,
            });
        }

        if (isAdmin) {
            items.push({
                title: 'Empresas',
                href: '/admin/empresas',
                icon: Settings,
            });
            items.push({
                title: 'Usuarios',
                href: '/admin/usuarios',
                icon: Users,
            });
            items.push({
                title: 'Moderadores',
                href: '/admin/moderadores',
                icon: Users,
            });
        }

        /*
        if (isAdmin) {
            items.push({
                title: 'Admin',
                href: '/admin',
                icon: Settings,
            });
        }
        */

        return items;
    });

    const footerNavItems: NavItem[] = [
        {
            title: 'Repositorio',
            href: 'https://github.com/laravel/svelte-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentacion',
            href: 'https://laravel.com/docs/starter-kits#svelte',
            icon: BookOpen,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
