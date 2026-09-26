<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Bug from '@lucide/svelte/icons/bug';
    import Shield from '@lucide/svelte/icons/shield';
    import Award from '@lucide/svelte/icons/award';
    import Key from '@lucide/svelte/icons/key';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';
    import Settings from '@lucide/svelte/icons/settings';
    import Users from '@lucide/svelte/icons/users';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Trophy from '@lucide/svelte/icons/trophy';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
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
    import { index as programasIndex } from '@/routes/programas';
    import type { NavItem } from '@/types';
    import type { CuentaEstado } from '@/lib/rangos';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const userRoles = $derived(
        (page.props.userRoles as string[]) ?? (page.props.auth?.user?.roles as string[]) ?? [],
    );

    const isAdmin = $derived(userRoles.includes('administrador'));
    const isInvestigador = $derived(userRoles.includes('investigador'));
    const isEmpresa = $derived(userRoles.includes('empresa'));
    const isModerador = $derived(userRoles.includes('moderador'));
    const cuenta = $derived(page.props.cuenta as CuentaEstado | null | undefined);

    const mainNavItems = $derived.by(() => {
        const items: NavItem[] = [
            {
                title: 'Panel Principal',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Salón de la Fama',
                href: '/hall-of-fame',
                icon: Trophy,
            },
        ];

        if (isModerador) {
            items.push({
                title: 'Moderación',
                href: '/moderacion',
                icon: ClipboardCheck,
            });
        }

        // El admin no triaja reportes ni ve la cola de moderación, pero sí resuelve
        // apelaciones (arbitraje de última instancia exclusivo para garantizar imparcialidad).
        if (isAdmin) {
            items.push({
                title: 'Apelaciones',
                href: '/moderacion/apelaciones',
                icon: MessageSquare,
            });
        }

        if (isInvestigador) {
            items.push({
                title: 'Mis Reportes',
                href: reportesIndex(),
                icon: Bug,
            });
        }

        if (isEmpresa) {
            items.push({
                title: 'Reportes recibidos',
                href: '/empresa/reportes',
                icon: Bug,
            });
        }

        if (isModerador && !isInvestigador && !isEmpresa) {
            items.push({
                title: 'Todos los reportes',
                href: reportesIndex(),
                icon: Bug,
            });
        }


        if (isInvestigador) {
            items.push({
                title: 'Programas',
                href: programasIndex(),
                icon: Shield,
            });
        }

        /*
        if (isInvestigador || isAdmin) {
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
            items.push({
                title: 'Mis apelaciones',
                href: '/reputacion/apelaciones',
                icon: MessageSquare,
            });
        }


        if (isEmpresa) {
            items.push({
                title: 'Panel empresa',
                href: '/empresa',
                icon: Shield,
            });
        }

        return items;
    });

    // El admin no gestiona programas ni reportes (eso es de la empresa/moderador):
    // su menú es solo gestión de usuarios, configuración del sistema y auditoría.
    const adminNavItems = $derived.by((): NavItem[] => {
        if (!isAdmin) return [];

        return [
            { title: 'Empresas', href: '/admin/empresas', icon: Building2 },
            { title: 'Usuarios', href: '/admin/usuarios', icon: Users },
            { title: 'Moderadores', href: '/admin/moderadores', icon: ShieldCheck },
            { title: 'Sanciones', href: '/admin/sanciones', icon: ShieldAlert },
            { title: 'Auditoría', href: '/admin/auditoria', icon: ClipboardList },
            { title: 'Config. Reputación', href: '/admin/config/reputacion', icon: Settings },
            { title: 'Claves PGP', href: '/admin/pgp', icon: Key },
        ];
    });
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
        {#if isAdmin}
            <NavMain items={adminNavItems} groupLabel="Administración" />
        {/if}
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
