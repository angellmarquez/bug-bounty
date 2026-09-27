<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Mis certificados', href: '/certificados' }],
    };
</script>

<script lang="ts">
    import Award from '@lucide/svelte/icons/award';
    import Check from '@lucide/svelte/icons/check';
    import Copy from '@lucide/svelte/icons/copy';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import FileBadge from '@lucide/svelte/icons/file-badge';
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/EmptyState.svelte';
    import PageHeader from '@/components/PageHeader.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import type { Severidad } from '@/types/enums';

    type CertificadoResumen = {
        reporte_id: number;
        numero_reporte: string;
        titulo: string;
        severidad: string;
        cvss_score: number;
        programa_nombre: string;
        empresa_nombre: string | null;
        fecha_resolucion: string | null;
        codigo: string | null;
        emitido_en: string | null;
        url_verificacion: string | null;
    };

    let { certificados = [] }: { certificados?: CertificadoResumen[] } = $props();

    let copiado = $state<string | null>(null);

    function copiar(url: string) {
        navigator.clipboard.writeText(url).then(() => {
            copiado = url;
            setTimeout(() => {
                if (copiado === url) copiado = null;
            }, 2500);
        });
    }

    function formatearFecha(iso: string | null): string {
        if (!iso) return '—';
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' }).format(new Date(iso));
    }
</script>

<AppHead title="Mis certificados" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <PageHeader
        title="Mis certificados"
        description="Un certificado por cada vulnerabilidad que ayudaste a resolver. Descárgalos o comparte su enlace de verificación cuando quieras."
    />

    {#if certificados.length === 0}
        <EmptyState
            icon={FileBadge}
            title="Todavía no tienes certificados"
            description="Cuando una empresa cierre como resuelto uno de tus informes, su certificado aparecerá aquí."
        />
    {:else}
        <p class="text-sm text-muted-foreground">
            {certificados.length === 1 ? '1 certificado' : `${certificados.length} certificados`}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            {#each certificados as c (c.reporte_id)}
                <div data-test="certificado">
                    <Card class="h-full">
                        <CardHeader>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <CardTitle class="leading-snug">{c.titulo}</CardTitle>
                                    <CardDescription>
                                        {c.numero_reporte} · {c.programa_nombre}{c.empresa_nombre ? ` · ${c.empresa_nombre}` : ''}
                                    </CardDescription>
                                </div>
                                <Award class="size-5 shrink-0 text-primary" />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <SeverityBadge severidad={c.severidad as Severidad} />
                                <span class="font-mono text-xs font-bold">CVSS {Number(c.cvss_score).toFixed(1)}</span>
                                <span class="text-xs text-muted-foreground">Resuelto el {formatearFecha(c.fecha_resolucion)}</span>
                            </div>

                            {#if c.codigo}
                                <p class="text-xs text-muted-foreground">
                                    Código <span class="font-mono font-semibold text-foreground">{c.codigo}</span>
                                </p>
                            {:else}
                                <p class="text-xs text-muted-foreground">El certificado se emitirá al abrirlo.</p>
                            {/if}

                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" href={`/reportes/${c.reporte_id}/certificado`}>
                                    <FileBadge class="mr-1.5 size-4" />
                                    Ver / descargar
                                </Button>
                                {#if c.url_verificacion}
                                    {@const url = c.url_verificacion}
                                    <Button size="sm" variant="outline" onclick={() => copiar(url)}>
                                        {#if copiado === url}
                                            <Check class="mr-1.5 size-4 text-primary" />
                                            Enlace copiado
                                        {:else}
                                            <Copy class="mr-1.5 size-4" />
                                            Copiar enlace
                                        {/if}
                                    </Button>
                                    <Button size="sm" variant="ghost" href={url} target="_blank">
                                        <ExternalLink class="mr-1.5 size-4" />
                                        Verificar
                                    </Button>
                                {/if}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            {/each}
        </div>
    {/if}
</div>
