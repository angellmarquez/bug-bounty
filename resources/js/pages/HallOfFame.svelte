<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            {
                title: 'Salón de la Fama',
                href: '/hall-of-fame',
            },
        ],
    };
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Trophy from '@lucide/svelte/icons/trophy';
    import Medal from '@lucide/svelte/icons/medal';
    import Crown from '@lucide/svelte/icons/crown';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Users from '@lucide/svelte/icons/users';
    import Sparkles from '@lucide/svelte/icons/sparkles';
    import AppHead from '@/components/AppHead.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import type { GlobalLeaderboardStats, HallOfFameRankingItem } from '@/types/domain';

    const periodo = $derived((page.props.periodo as string) ?? 'historico');
    const ranking = $derived((page.props.ranking as HallOfFameRankingItem[]) ?? []);
    const stats = $derived(
        (page.props.metricas as GlobalLeaderboardStats) ?? {
            total_investigadores: 0,
            total_vulnerabilidades_resueltas: 0,
            puntos_totales_repartidos: 0,
        },
    );


    const primero = $derived(ranking.find((r) => r.posicion === 1));
    const segundo = $derived(ranking.find((r) => r.posicion === 2));
    const tercero = $derived(ranking.find((r) => r.posicion === 3));
    const resto = $derived(ranking.filter((r) => r.posicion > 3));

    function iniciales(nombre: string): string {
        return nombre
            .split(' ')
            .slice(0, 2)
            .map((p) => p[0])
            .join('')
            .toUpperCase();
    }
</script>

<AppHead title="Salón de la Fama" />

<div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
    <PageHeader
        title="Salón de la Fama"
        description="Reconocimiento a los investigadores de seguridad más destacados en divulgación coordinada."
    />

    <!-- Métricas Globales de la Comunidad -->
    <div class="grid gap-4 sm:grid-cols-3">
        <Card class="transition-colors hover:border-primary">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardDescription>Cazadores activos</CardDescription>
                <Users class="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{stats.total_investigadores}</div>
                <p class="text-xs text-muted-foreground">Investigadores registrados</p>
            </CardContent>
        </Card>

        <Card class="transition-colors hover:border-primary">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardDescription>Vulnerabilidades resueltas</CardDescription>
                <ShieldCheck class="size-4 text-chart-1" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{stats.total_vulnerabilidades_resueltas}</div>
                <p class="text-xs text-muted-foreground">Impacto remediado</p>
            </CardContent>
        </Card>

        <Card class="transition-colors hover:border-primary">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardDescription>Puntos otorgados</CardDescription>
                <Trophy class="size-4 text-chart-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{stats.puntos_totales_repartidos.toLocaleString()}</div>
                <p class="text-xs text-muted-foreground">Reputación total en ledger</p>
            </CardContent>
        </Card>
    </div>

    <!-- Pestañas de Filtro Temporal -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-3">
        <div class="flex gap-2">
            <Button
                variant={periodo === 'historico' ? 'default' : 'outline'}
                size="sm"
                href="/hall-of-fame?periodo=historico"
            >
                <Trophy class="mr-1.5 size-3.5" />
                Histórico
            </Button>
            <Button
                variant={periodo === 'anual' ? 'default' : 'outline'}
                size="sm"
                href="/hall-of-fame?periodo=anual"
            >
                <Sparkles class="mr-1.5 size-3.5" />
                Este Año
            </Button>
            <Button
                variant={periodo === 'mensual' ? 'default' : 'outline'}
                size="sm"
                href="/hall-of-fame?periodo=mensual"
            >
                <Medal class="mr-1.5 size-3.5" />
                Este Mes
            </Button>
        </div>

        <p class="text-xs text-muted-foreground">
            Los puntos se calculan automáticamente según el impacto CVSS de cada reporte.
        </p>
    </div>

    {#if ranking.length === 0}
        <Card class="p-8 text-center">
            <div class="flex flex-col items-center justify-center gap-2">
                <Trophy class="size-10 text-muted-foreground opacity-40" />
                <p class="text-base font-semibold">Sin clasificados en este periodo</p>
                <p class="text-sm text-muted-foreground">
                    Sé el primero en reportar una vulnerabilidad válida para entrar al Salón de la Fama.
                </p>
                <Button href="/programas" class="mt-2" size="sm">Explorar programas</Button>
            </div>
        </Card>
    {:else}
        <!-- Podio Top 3 Destacado -->
        <div class="grid gap-4 md:grid-cols-3 md:items-end">
            <!-- 2do Lugar (Plata) -->
            {#if segundo}
                <Card class="relative border-border/40 bg-gradient-to-t from-muted/30 to-transparent order-2 md:order-1">
                    <div class="absolute -top-3 left-4 flex size-7 items-center justify-center rounded-full bg-muted-foreground font-bold text-background shadow">
                        <Medal class="size-4" aria-label="Segundo puesto" />
                    </div>
                    <CardHeader class="pt-6 text-center">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full border border-border/50 bg-muted text-sm font-bold text-foreground">
                            {iniciales(segundo.name)}
                        </div>
                        <CardTitle class="mt-2 text-lg">{segundo.name}</CardTitle>
                        <div class="mt-1 flex justify-center">
                            <RangoBadge puntos={segundo.puntos} />
                        </div>
                    </CardHeader>
                    <CardContent class="text-center space-y-2">
                        <div class="text-2xl font-black text-foreground">{segundo.puntos} <span class="text-xs font-normal text-muted-foreground">pts</span></div>
                        <p class="text-xs text-muted-foreground">{segundo.reportes_resueltos} vulnerabilidades resueltas</p>
                        <div class="flex justify-center gap-1.5 pt-1 text-[11px]">
                            {#if segundo.severidades.critica > 0}
                                <span class="rounded bg-chart-3/20 px-1.5 py-0.5 text-chart-3">{segundo.severidades.critica} críticas</span>
                            {/if}
                            {#if segundo.severidades.alta > 0}
                                <span class="rounded bg-chart-4/20 px-1.5 py-0.5 text-chart-4">{segundo.severidades.alta} altas</span>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/if}

            <!-- 1er Lugar (Oro) -->
            {#if primero}
                <Card class="relative border-aviso/60 bg-gradient-to-t from-aviso/20 to-transparent shadow-lg shadow-aviso/10 order-1 md:order-2 md:-translate-y-2">
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2 flex items-center gap-1 rounded-full border border-aviso/60 bg-aviso px-3 py-0.5 text-xs font-bold text-background shadow-md">
                        <Crown class="size-3.5 fill-current" />
                        CAMPEÓN
                    </div>
                    <CardHeader class="pt-7 text-center">
                        <div class="mx-auto flex size-16 items-center justify-center rounded-full border-2 border-aviso bg-aviso/15 text-base font-black text-aviso shadow">
                            {iniciales(primero.name)}
                        </div>
                        <CardTitle class="mt-2 text-xl font-bold">{primero.name}</CardTitle>
                        <div class="mt-1 flex justify-center">
                            <RangoBadge puntos={primero.puntos} />
                        </div>
                    </CardHeader>
                    <CardContent class="text-center space-y-2">
                        <div class="text-3xl font-black text-aviso">{primero.puntos} <span class="text-xs font-normal text-muted-foreground">pts</span></div>
                        <p class="text-xs text-muted-foreground">{primero.reportes_resueltos} vulnerabilidades resueltas</p>
                        <div class="flex justify-center gap-1.5 pt-1 text-[11px]">
                            {#if primero.severidades.critica > 0}
                                <span class="rounded bg-chart-3/20 px-1.5 py-0.5 font-medium text-chart-3">{primero.severidades.critica} críticas</span>
                            {/if}
                            {#if primero.severidades.alta > 0}
                                <span class="rounded bg-chart-4/20 px-1.5 py-0.5 font-medium text-chart-4">{primero.severidades.alta} altas</span>
                            {/if}
                            {#if primero.severidades.media > 0}
                                <span class="rounded bg-chart-2/20 px-1.5 py-0.5 font-medium text-chart-2">{primero.severidades.media} medias</span>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/if}

            <!-- 3er Lugar (Bronce) -->
            {#if tercero}
                <Card class="relative border-aviso/40 bg-gradient-to-t from-aviso/20 to-transparent order-3">
                    <div class="absolute -top-3 left-4 flex size-7 items-center justify-center rounded-full bg-aviso font-bold text-background shadow">
                        <Medal class="size-4" aria-label="Tercer puesto" />
                    </div>
                    <CardHeader class="pt-6 text-center">
                        <div class="mx-auto flex size-12 items-center justify-center rounded-full border border-aviso/50 bg-aviso/15 text-sm font-bold text-aviso">
                            {iniciales(tercero.name)}
                        </div>
                        <CardTitle class="mt-2 text-lg">{tercero.name}</CardTitle>
                        <div class="mt-1 flex justify-center">
                            <RangoBadge puntos={tercero.puntos} />
                        </div>
                    </CardHeader>
                    <CardContent class="text-center space-y-2">
                        <div class="text-2xl font-black text-aviso">{tercero.puntos} <span class="text-xs font-normal text-muted-foreground">pts</span></div>
                        <p class="text-xs text-muted-foreground">{tercero.reportes_resueltos} vulnerabilidades resueltas</p>
                        <div class="flex justify-center gap-1.5 pt-1 text-[11px]">
                            {#if tercero.severidades.critica > 0}
                                <span class="rounded bg-chart-3/20 px-1.5 py-0.5 text-chart-3">{tercero.severidades.critica} críticas</span>
                            {/if}
                            {#if tercero.severidades.alta > 0}
                                <span class="rounded bg-chart-4/20 px-1.5 py-0.5 text-chart-4">{tercero.severidades.alta} altas</span>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/if}
        </div>

        <!-- Tabla General (Puestos del 4 en adelante) -->
        {#if resto.length > 0}
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Clasificación General</CardTitle>
                    <CardDescription>Investigadores clasificados a partir del 4º puesto.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    {#each resto as hacker (hacker.id)}
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/60 p-3 transition-colors hover:border-primary/50">
                            <div class="flex items-center gap-3">
                                <span class="flex size-7 items-center justify-center rounded-md bg-muted text-xs font-bold text-muted-foreground">
                                    #{hacker.posicion}
                                </span>
                                <div>
                                    <p class="text-sm font-semibold">{hacker.name}</p>
                                    <div class="mt-0.5 flex items-center gap-2">
                                        <RangoBadge puntos={hacker.puntos} />
                                        <span class="text-xs text-muted-foreground">· {hacker.reportes_resueltos} resueltos</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="hidden sm:flex gap-1.5 text-[11px]">
                                    {#if hacker.severidades.critica > 0}
                                        <span class="rounded bg-chart-3/15 px-1.5 py-0.5 text-chart-3">{hacker.severidades.critica} C</span>
                                    {/if}
                                    {#if hacker.severidades.alta > 0}
                                        <span class="rounded bg-chart-4/15 px-1.5 py-0.5 text-chart-4">{hacker.severidades.alta} A</span>
                                    {/if}
                                    {#if hacker.severidades.media > 0}
                                        <span class="rounded bg-chart-2/15 px-1.5 py-0.5 text-chart-2">{hacker.severidades.media} M</span>
                                    {/if}
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-primary">{hacker.puntos} pts</p>
                                </div>
                            </div>
                        </div>
                    {/each}
                </CardContent>
            </Card>
        {/if}
    {/if}
</div>
