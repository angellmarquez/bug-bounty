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
    import { index as reportesIndex, create as reportesCreate } from '@/routes/reportes';
    import { index as programasIndex, gestion as gestionProgramas } from '@/routes/programas';
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import EstadoCuentaCard from '@/components/EstadoCuentaCard.svelte';
    import { Button } from '@/components/ui/button';
    import Plus from '@lucide/svelte/icons/plus';
    import Bug from '@lucide/svelte/icons/bug';
    import Shield from '@lucide/svelte/icons/shield';
    import Settings from '@lucide/svelte/icons/settings';
    import Building2 from '@lucide/svelte/icons/building-2';
    import UserCog from '@lucide/svelte/icons/user-cog';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import EstadoProgreso from '@/components/EstadoProgreso.svelte';
    import ReportesTimeline from '@/components/ReportesTimeline.svelte';
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

    // Vista rápida: los últimos informes ya enviados (los borradores no cuentan).
    const ultimosEnviados = $derived(
        misReportes.filter((reporte) => reporte.estado !== 'borrador').slice(0, 10),
    );

    // Los informes agrupados por programa, para seguir el avance de cada uno.
    const informesPorPrograma = $derived.by(() => {
        const grupos = new Map<string, { nombre: string; informes: typeof misReportes }>();
        for (const reporte of misReportes) {
            const nombre = reporte.programa?.nombre ?? 'Sin programa';
            const grupo = grupos.get(nombre) ?? { nombre, informes: [] };
            grupo.informes.push(reporte);
            grupos.set(nombre, grupo);
        }
        return [...grupos.values()];
    });

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
    const isGestion = $derived(userRoles.includes('gestion'));

    const greeting = $derived.by(() => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Buenos dias';
        if (hour < 19) return 'Buenas tardes';
        return 'Buenas noches';
    });

    const statCards = $derived.by(() => {
        const cards = [
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
        ];

        if (isAdmin || isGestion) {
            cards.push({
                title: 'Programas Activos',
                value: stats.programas_activos,
                description: 'Programas de bug bounty',
                href: '/programas?estado=activo',
            });
        }

        return cards;
    });
</script>

<AppHead title="Dashboard" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="{greeting}, {user?.name ?? 'Usuario'}"
        description="Panel de control de la plataforma de divulgacion coordinada"
    />

    <EstadoCuentaCard />

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

    {#if isAdmin}
        <Card>
            <CardHeader>
                <CardTitle>Vista de Administrador</CardTitle>
                <CardDescription>
                    Tienes acceso total a la plataforma. Puedes gestionar
                    usuarios, programas, reportes y configuracion del sistema.
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
                        <Link href="/admin/moderadores" {...props}>
                            <UserCog class="mr-2 h-4 w-4" />
                            Gestionar moderadores
                        </Link>
                    {/snippet}
                </Button>
            </CardContent>
        </Card>
    {:else if isGestion}
        <Card>
            <CardHeader>
                <CardTitle>Vista de Gestion</CardTitle>
                <CardDescription>
                    Puedes triar reportes, asignar analistas y gestionar
                    programas de bug bounty.
                </CardDescription>
            </CardHeader>
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
        {#if ultimosEnviados.length > 0}
            <Card>
                <CardHeader>
                    <CardTitle>Últimos informes enviados</CardTitle>
                    <CardDescription>
                        Vista rápida: cada punto es uno de tus últimos informes y su color indica en qué
                        estado se encuentra. Pasa el cursor o haz clic para ver el detalle.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ReportesTimeline reportes={ultimosEnviados} />
                </CardContent>
            </Card>
        {/if}

        <Card>
            <CardHeader>
                <CardTitle>Estado de mis informes por programa</CardTitle>
                <CardDescription>
                    El avance de cada informe: enviado, revisión del moderador, validación, reparación y cierre.
                    Cada decisión del moderador aparece aquí.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                {#if misReportes.length === 0}
                    <div class="flex flex-col items-start gap-3">
                        <p class="text-sm text-muted-foreground">
                            Todavía no has enviado informes. Elige un programa y reporta tu primer hallazgo.
                        </p>
                        <Button href="/programas">Explorar programas</Button>
                    </div>
                {:else}
                    {#each informesPorPrograma as grupo (grupo.nombre)}
                        <div class="space-y-3">
                            <h3 class="text-sm font-semibold">{grupo.nombre}</h3>
                            {#each grupo.informes as informe (informe.id)}
                                <div class="space-y-3 rounded-md border p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <Link href={`/reportes/${informe.id}`} class="text-sm font-medium hover:underline">
                                            {informe.numero_reporte} · {informe.titulo}
                                        </Link>
                                        <StateBadge estado={informe.estado} />
                                    </div>
                                    <EstadoProgreso estado={informe.estado} />
                                    {#if informe.ultimo_evento}
                                        <p class="text-xs text-muted-foreground">
                                            Último movimiento ({formatearMovimiento(informe.ultimo_evento.fecha)}):
                                            {informe.ultimo_evento.nota ?? informe.ultimo_evento.tipo}
                                        </p>
                                    {/if}
                                    <Link href={`/reportes/${informe.id}`} class="text-xs text-primary hover:underline">
                                        Ver línea de tiempo completa
                                    </Link>
                                </div>
                            {/each}
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>
    {/if}

    {#if userRoles.includes('moderador') || isAdmin}
        <Card>
            <CardHeader>
                <CardTitle>Cola de moderación</CardTitle>
                <CardDescription>
                    Revisa los informes que los investigadores envían a los programas: valida, rechaza,
                    penaliza o marca duplicados. Cada decisión queda en la línea de tiempo del investigador.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-4">
                {#if roleStats.tipo === 'moderador' || roleStats.tipo === 'administrador'}
                    <p class="text-sm">
                        <span class="text-2xl font-bold text-chart-4">{roleStats.por_revisar}</span>
                        <span class="ml-1 text-muted-foreground">informes por revisar</span>
                    </p>
                {/if}
                <Button href="/moderacion">
                    Revisar informes
                </Button>
            </CardContent>
        </Card>
    {/if}

    {#if roleStats.tipo === 'empresa'}
        <Card>
            <CardHeader><CardTitle>Resumen empresarial</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-2xl font-bold">{roleStats.programas_total}</p><p class="text-xs text-muted-foreground">Programas totales</p></div>
                <div><p class="text-2xl font-bold">{roleStats.programas_activos}</p><p class="text-xs text-muted-foreground">Programas activos</p></div>
                <div><p class="text-2xl font-bold">{roleStats.miembros}</p><p class="text-xs text-muted-foreground">Miembros</p></div>
                <div><p class="text-2xl font-bold">{roleStats.reportes_recibidos}</p><p class="text-xs text-muted-foreground">Reportes recibidos</p></div>
            </CardContent>
        </Card>
    {:else if roleStats.tipo === 'moderador'}
        <Card>
            <CardHeader><CardTitle>Resumen de moderación</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-2xl font-bold">{roleStats.pendientes_revision}</p><p class="text-xs text-muted-foreground">Pendientes de revisión</p></div>
                <div><p class="text-2xl font-bold">{roleStats.validados}</p><p class="text-xs text-muted-foreground">Validados</p></div>
                <div><p class="text-2xl font-bold">{roleStats.rechazados}</p><p class="text-xs text-muted-foreground">Rechazados</p></div>
                <div><p class="text-2xl font-bold">{roleStats.sanciones_aplicadas}</p><p class="text-xs text-muted-foreground">Sanciones aplicadas</p></div>
            </CardContent>
        </Card>
    {:else if roleStats.tipo === 'administrador'}
        <Card>
            <CardHeader><CardTitle>Resumen administrativo</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-2xl font-bold">{roleStats.empresas_pendientes}</p><p class="text-xs text-muted-foreground">Empresas pendientes</p></div>
                <div><p class="text-2xl font-bold">{roleStats.empresas_aprobadas}</p><p class="text-xs text-muted-foreground">Empresas aprobadas</p></div>
                <div><p class="text-2xl font-bold">{roleStats.moderadores}</p><p class="text-xs text-muted-foreground">Moderadores</p></div>
                <div><p class="text-2xl font-bold">{roleStats.sanciones_activas}</p><p class="text-xs text-muted-foreground">Sanciones activas</p></div>
            </CardContent>
        </Card>
    {/if}

    <div class="space-y-4">
        <h3 class="text-lg font-medium">Acciones rapidas</h3>
        <div class="flex flex-wrap gap-4">
            <Button asChild>
                {#snippet children(props)}
                    <Link href={reportesCreate()} {...props}>
                        <Plus class="mr-2 h-4 w-4" />
                        Crear Reporte
                    </Link>
                {/snippet}
            </Button>
            <Button asChild variant="outline">
                {#snippet children(props)}
                    <Link href={programasIndex()} {...props}>
                        <Shield class="mr-2 h-4 w-4" />
                        Ver Programas
                    </Link>
                {/snippet}
            </Button>
            {#if isGestion || isAdmin}
                <Button asChild variant="outline">
                    {#snippet children(props)}
                        <Link href={gestionProgramas()} {...props}>
                            <Settings class="mr-2 h-4 w-4" />
                            Gestionar Programas
                        </Link>
                    {/snippet}
                </Button>
            {/if}
        </div>
    </div>
</div>
