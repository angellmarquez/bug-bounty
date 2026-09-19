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
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { DashboardStats } from '@/types/domain';

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
        (user?.roles as string[]) ?? [],
    );

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
            },
            {
                title: 'Reportes Abiertos',
                value: stats.reportes_abiertos,
                description: 'En ciclo de triaje',
            },
            {
                title: 'Reportes Cerrados',
                value: stats.reportes_cerrados,
                description: 'Resueltos',
            },
        ];

        if (isAdmin || isGestion) {
            cards.push({
                title: 'Programas Activos',
                value: stats.programas_activos,
                description: 'Programas de bug bounty',
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

    {#if user?.reputation_score !== undefined}
        <Card>
            <CardHeader>
                <CardDescription>Tu reputacion</CardDescription>
                <CardTitle class="text-3xl font-bold text-primary">
                    {user.reputation_score} pts
                </CardTitle>
            </CardHeader>
        </Card>
    {/if}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {#each statCards as card (card.title)}
            <Card>
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
                    Presenta reportes de vulnerabilidades, gestiona tus claves
                    PGP y revisa el estado de tus hallazgos.
                </CardDescription>
            </CardHeader>
        </Card>
    {/if}
</div>
