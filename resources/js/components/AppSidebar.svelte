<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Bug from '@lucide/svelte/icons/bug';
    import Shield from '@lucide/svelte/icons/shield';
    import Award from '@lucide/svelte/icons/award';
    import Key from '@lucide/svelte/icons/key';
    import ClipboardCheck from '@lucide/svelte/icons/clipboard-check';
    import Settings from '@lucide/svelte/icons/settings';
    import Users from '@lucide/svelte/icons/users';
    import MessageSquare from '@lucide/svelte/icons/message-square';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Mail from '@lucide/svelte/icons/mail';
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
    import { index as programasIndex, gestion as gestionProgramas } from '@/routes/programas';
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
    const esPublicador = $derived(cuenta?.empresa?.rol_interno === 'publicador');
    const invitacionesPendientes = $derived(cuenta?.invitaciones_pendientes ?? 0);

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
            items.push({
                title: 'Apelaciones',
                href: '/moderacion/apelaciones',
                icon: MessageSquare,
            });
        }

        if (isInvestigador || isAdmin || isEmpresa || isModerador) {
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

        if (isInvestigador || isAdmin) {
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

        // Un investigador invitado por una empresa publica sus programas desde aquí.
        if (esPublicador) {
            items.push({
                title: 'Mi empresa',
                href: '/gestion/programas',
                icon: Building2,
            });
        }

        if (invitacionesPendientes > 0) {
            items.push({
                title: 'Invitaciones',
                href: '/invitaciones',
                icon: Mail,
            });
        }

        if (isAdmin) {
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
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
