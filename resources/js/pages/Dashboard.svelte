<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { index as reportesIndex } from '@/routes/reportes';
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EstadoCuentaCard from '@/components/EstadoCuentaCard.svelte';
    import type { CuentaEstado } from '@/lib/rangos';
    import { Button } from '@/components/ui/button';
    import Building2 from '@lucide/svelte/icons/building-2';
    import UserCog from '@lucide/svelte/icons/user-cog';
    import Users from '@lucide/svelte/icons/users';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Bug from '@lucide/svelte/icons/bug';
    import EmptyState from '@/components/EmptyState.svelte';
    import RolesUsuario from '@/components/RolesUsuario.svelte';
    import StateBadge from '@/components/StateBadge.svelte';
    import type { DashboardRoleStats, DashboardStats } from '@/types/domain';
    import type { EstadoReporte } from '@/types/enums';

    const user = $derived(page.props.auth?.user);
    const stats = $derived(
        (page.props.stats as DashboardStats) ?? {
            reportes_total: 0,
            reportes_abiertos: 0,
            reportes_cerrados: 0,
            reportes_borrador: 0,
            programas_activos: 0,
            reputacion: 0,
        },
    );

    const userRoles = $derived(
        (page.props.userRoles as string[]) ?? (user?.roles as string[]) ?? [],
    );
    const roleStats = $derived((page.props.roleStats as DashboardRoleStats) ?? {});
    const rolEmpresa = $derived(roleStats.empresa);
    const rolModerador = $derived(roleStats.moderador);
    const rolAdmin = $derived(roleStats.administrador);
    const misReportes = $derived(

        (page.props.misReportes as {
            id: number;
            numero_reporte: string;
            titulo: string;
            estado: EstadoReporte;
            fecha: string;
            programa?: { id: number; nombre: string } | null;
            ultimo_evento?: { tipo: string; nota: string | null; fecha: string | null } | null;
        }[]) ?? [],
    );

    // Vista limpia: hasta 5 informes recientes (evita saturar la pantalla con decenas de reportes).
    const reportesRecientes = $derived(
        misReportes.slice(0, 5),
    );

    function formatearMovimiento(fecha: string | null | undefined): string {
        if (!fecha) return '';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(fecha));
    }

    const isAdmin = $derived(userRoles.includes('administrador'));
    const cuenta = $derived(page.props.cuenta as CuentaEstado | null | undefined);

    const greeting = $derived.by(() => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Buenos días';
        if (hour < 19) return 'Buenas tardes';
        return 'Buenas noches';
    });

    // El admin no participa en el día a día de los reportes: sin tarjetas de
    // volumen/estado de reportes en su panel (eso es de investigador/moderador/empresa).
    const statCards = $derived.by(() => {
        if (isAdmin) return [];

        return [
            {
                title: 'Total Reportes',
                value: stats.reportes_total,
                description: 'Reportes presentados',
                href: reportesIndex(),
            },
            {
                title: 'Reportes Abiertos',
                value: stats.reportes_abiertos,
                description: 'En ciclo de triaje',
                href: '/reportes?estado=en_revision',
            },
            {
                title: 'Reportes Cerrados',
                value: stats.reportes_cerrados,
                description: 'Resueltos',
                href: '/reportes?estado=cerrado',
            },
            {
                title: 'Salón de la Fama',
                value: '🏆 Ranking',
                description: 'Líderes de la comunidad',
                href: '/hall-of-fame',
            },
        ];
    });
</script>

<AppHead title="Dashboard" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="{greeting}, {user?.name ?? 'Usuario'}"
        description="Panel de control de la plataforma de divulgación coordinada"
    />

    <RolesUsuario />

    <EstadoCuentaCard />

    {#if statCards.length > 0}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {#each statCards as card (card.title)}
            <Link href={card.href} class="block h-full">
                <Card class="h-full transition-colors hover:border-primary">
                    <CardHeader class="pb-2">
                        <CardDescription>{card.description}</CardDescription>
                        <CardTitle class="text-2xl font-bold">
                            {card.value}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-muted-foreground">
                            {card.title}
                        </p>
                    </CardContent>
                </Card>
            </Link>
        {/each}
    </div>
    {/if}

    {#if isAdmin}
        <Card>
            <CardHeader>
                <CardTitle>Vista de Administrador</CardTitle>
                <CardDescription>
                    No participas en el día a día de los reportes: eso es del investigador, el moderador
                    y la empresa. Tu función es gestión de usuarios, configuración del sistema y auditoría.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-3">
                <Button asChild>
                    {#snippet children(props)}
                        <Link href="/admin/empresas" {...props}>
                            <Building2 class="mr-2 h-4 w-4" />
                            Aprobar empresas
                        </Link>
                    {/snippet}
                </Button>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <Link href="/admin/usuarios" {...props}>
                            <Users class="mr-2 h-4 w-4" />
                            Gestionar usuarios
                        </Link>
                    {/snippet}
                </Button>
                <Button variant="outline" asChild>
                    {#snippet children(props)}
                        <Link href="/admin/moderadores" {...props}>
                            <UserCog class="mr-2 h-4 w-4" />
                            Dar de alta moderadores
                        </Link>
                    {/snippet}
                </Button>
            </CardContent>
        </Card>
    {:else}
        <Card>
            <CardHeader>
                <CardTitle>Vista de Investigador</CardTitle>
                <CardDescription>
                    Elige un programa, presenta tus hallazgos con su formulario
                    y sigue el estado de cada informe desde aquí.
                </CardDescription>
            </CardHeader>
        </Card>
    {/if}

    {#if userRoles.includes('investigador')}
        <Card>
            <CardHeader class="flex flex-row items-center justify-between pb-3">
                <div class="space-y-1">
                    <CardTitle class="text-base font-semibold">Actividad reciente de reportes</CardTitle>
                    <CardDescription>
                        Tus últimos hallazgos presentados y su estado actual en la plataforma.
                    </CardDescription>
                </div>
                {#if misReportes.length > 0}
                    <Button variant="outline" size="sm" href={reportesIndex()}>
                        Ver todos ({stats.reportes_total})
                        <ChevronRight class="ml-1 h-3.5 w-3.5" />
                    </Button>
                {/if}
            </CardHeader>
            <CardContent>
                {#if misReportes.length === 0}
                    <EmptyState
                        icon={Bug}
                        title="Todavía no has enviado informes"
                        description="Elige un programa de una empresa y reporta tu primer hallazgo de seguridad."
                    >
                        <Button href="/programas">Explorar programas</Button>
                    </EmptyState>
                {:else}
                    <div class="divide-y rounded-md border">
                        {#each reportesRecientes as informe (informe.id)}
                            <div class="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between transition-colors hover:bg-muted/40">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs text-muted-foreground">{informe.numero_reporte}</span>
                                        <Link href={`/reportes/${informe.id}`} class="truncate text-sm font-medium hover:underline text-foreground">
                                            {informe.titulo}
                                        </Link>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        {#if informe.programa?.nombre}
                                            <span class="rounded bg-muted px-1.5 py-0.5 font-medium">{informe.programa.nombre}</span>
                                        {/if}
                                        {#if informe.ultimo_evento}
                                            <span>
                                                Último movimiento: {informe.ultimo_evento.nota ?? informe.ultimo_evento.tipo} ({formatearMovimiento(informe.ultimo_evento.fecha)})
                                            </span>
                                        {:else}
                                            <span>Presentado el {formatearMovimiento(informe.fecha)}</span>
                                        {/if}
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                                    <StateBadge estado={informe.estado} />
                                    <Button variant="ghost" size="icon" href={`/reportes/${informe.id}`} class="h-8 w-8 text-muted-foreground hover:text-foreground">
                                        <ChevronRight class="h-4 w-4" />
                                        <span class="sr-only">Ver reporte</span>
                                    </Button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </CardContent>
        </Card>
    {/if}

    {#if userRoles.includes('moderador')}
        <Card>
            <CardHeader>
                <CardTitle>Cola de moderación</CardTitle>
                <CardDescription>
                    Revisa los informes que los investigadores envían a los programas: valida, rechaza,
                    penaliza o marca duplicados. Cada decisión queda en la línea de tiempo del investigador.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-4">
                {#if rolModerador}
                    <p class="text-sm">
                        <span class="text-2xl font-bold text-chart-4">{rolModerador.por_revisar}</span>
                        <span class="ml-1 text-muted-foreground">informes por revisar</span>
                    </p>
                {/if}
                <Button href="/moderacion">
                    Revisar informes
                </Button>
            </CardContent>
        </Card>
    {/if}

    {#if rolEmpresa}
        <Card>
            <CardHeader><CardTitle>Resumen empresarial</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <div><p class="text-2xl font-bold">{rolEmpresa.programas_total}</p><p class="text-xs text-muted-foreground">Programas totales</p></div>
                <div><p class="text-2xl font-bold">{rolEmpresa.programas_activos}</p><p class="text-xs text-muted-foreground">Programas activos</p></div>
                <div><p class="text-2xl font-bold">{rolEmpresa.reportes_recibidos}</p><p class="text-xs text-muted-foreground">Reportes recibidos</p></div>
            </CardContent>
        </Card>
    {/if}

    {#if rolModerador}
        <Card>
            <CardHeader><CardTitle>Resumen de moderación</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-2xl font-bold">{rolModerador.pendientes_revision}</p><p class="text-xs text-muted-foreground">Pendientes de revisión</p></div>
                <div><p class="text-2xl font-bold">{rolModerador.validados}</p><p class="text-xs text-muted-foreground">Validados</p></div>
                <div><p class="text-2xl font-bold">{rolModerador.rechazados}</p><p class="text-xs text-muted-foreground">Rechazados</p></div>
                <div><p class="text-2xl font-bold">{rolModerador.sanciones_aplicadas}</p><p class="text-xs text-muted-foreground">Sanciones aplicadas</p></div>
            </CardContent>
        </Card>
    {/if}

    {#if rolAdmin}
        <Card>
            <CardHeader><CardTitle>Resumen administrativo</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-2xl font-bold">{rolAdmin.empresas_pendientes}</p><p class="text-xs text-muted-foreground">Empresas pendientes</p></div>
                <div><p class="text-2xl font-bold">{rolAdmin.empresas_aprobadas}</p><p class="text-xs text-muted-foreground">Empresas aprobadas</p></div>
                <div><p class="text-2xl font-bold">{rolAdmin.moderadores}</p><p class="text-xs text-muted-foreground">Moderadores</p></div>
                <div><p class="text-2xl font-bold">{rolAdmin.sanciones_activas}</p><p class="text-xs text-muted-foreground">Sanciones activas</p></div>
            </CardContent>
        </Card>
    {/if}
</div>

