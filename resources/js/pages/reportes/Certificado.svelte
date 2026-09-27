<script module lang="ts">
    export const layout = {
        title: 'Certificado de Divulgación Responsable',
        description: 'Certificado oficial con firma criptográfica PGP y huella de integridad SHA-256.',
    };
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import BotonVolver from '@/components/BotonVolver.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Printer from '@lucide/svelte/icons/printer';
    import Copy from '@lucide/svelte/icons/copy';
    import Check from '@lucide/svelte/icons/check';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Key from '@lucide/svelte/icons/key';
    import Lock from '@lucide/svelte/icons/lock';
    import Award from '@lucide/svelte/icons/award';
    import type { Severidad } from '@/types/enums';

    let {
        certificado,
        reporteId,
        urlVerificacion,
    }: {
        certificado: {
            id: number;
            codigo: string;
            huella: string;
            firma_pgp: string;
            clave_huella: string | null;
            datos: {
                numero_reporte: string;
                titulo: string;
                categoria?: string;
                severidad: string;
                cvss_score: number;
                cvss_vector?: string | null;
                programa_nombre: string;
                empresa_nombre: string;
                investigador_alias: string;
                investigador_id?: number;
                fecha_reporte: string;
                fecha_resolucion: string;
            };
            emitido_en: string;
        };
        reporteId: number;
        urlVerificacion: string;
    } = $props();

    let copiado = $state(false);
    let mostrandoFirma = $state(false);

    function copiarEnlace() {
        navigator.clipboard.writeText(urlVerificacion).then(() => {
            copiado = true;
            setTimeout(() => {
                copiado = false;
            }, 2500);
        });
    }

    function formatearFecha(iso?: string | null): string {
        if (!iso) return 'N/A';
        try {
            return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
        } catch {
            return iso;
        }
    }

    // El autor vuelve a su lista de certificados (la puede abrir aunque esté suspendido);
    // la empresa, el moderador o el admin, al informe.
    const esAutor = $derived(page.props.auth?.user?.id === certificado.datos.investigador_id);

    // La huella en grupos de 8 se lee y se compara mejor (y parte bien en el papel).
    const huellaAgrupada = $derived(certificado.huella.match(/.{1,8}/g)?.join(' ') ?? certificado.huella);
</script>

<AppHead title={`Certificado ${certificado.codigo}`} />

<div class="flex h-full flex-1 flex-col gap-6 p-4 print:gap-0 print:p-0">
    <!-- Barra de acciones (oculta al imprimir) -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4 print:hidden">
        <div class="flex items-center gap-3">
            {#if esAutor}
                <BotonVolver href="/certificados" etiqueta="Mis certificados" />
            {:else}
                <BotonVolver href={`/reportes/${reporteId}`} etiqueta="Volver al informe" />
            {/if}
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold">
                    <Award class="size-5 text-primary" />
                    Certificado de divulgación
                </h1>
                <p class="text-xs text-muted-foreground">
                    Código de verificación: <span class="font-mono font-semibold text-foreground">{certificado.codigo}</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" onclick={copiarEnlace}>
                {#if copiado}
                    <Check class="mr-1.5 size-4 text-primary" />
                    Enlace copiado
                {:else}
                    <Copy class="mr-1.5 size-4" />
                    Copiar enlace público
                {/if}
            </Button>

            <Button variant="outline" size="sm" href={urlVerificacion} target="_blank">
                <ExternalLink class="mr-1.5 size-4" />
                Verificador público
            </Button>

            <Button size="sm" onclick={() => window.print()}>
                <Printer class="mr-1.5 size-4" />
                Imprimir / Guardar PDF
            </Button>
        </div>
    </div>

    <!-- Documento del certificado -->
    <div class="mx-auto w-full max-w-4xl print:max-w-none">
        <article
            class="certificado relative overflow-hidden rounded-2xl border-2 border-primary/40 bg-card p-8 shadow-2xl md:p-12 print:rounded-none print:border-2 print:border-black print:p-7 print:shadow-none"
        >
            <!-- Sello decorativo de fondo -->
            <div class="pointer-events-none absolute -top-16 -right-16 opacity-5 print:hidden">
                <ShieldCheck class="size-80 text-primary" />
            </div>

            <!-- Encabezado -->
            <header class="flex flex-col items-center gap-2 border-b border-border/80 pb-6 text-center print:pb-4">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground print:size-7">
                        <AppLogoIcon class="size-5 print:size-4" />
                    </span>
                    <span class="font-display text-lg font-semibold tracking-tight">huella</span>
                </div>
                <div class="mt-1 inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold tracking-wider text-primary uppercase">
                    <ShieldCheck class="size-4" />
                    Divulgación coordinada de vulnerabilidades
                </div>
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight md:text-3xl print:text-2xl">
                    CERTIFICADO DE DIVULGACIÓN RESPONSABLE
                </h2>
                <p class="max-w-xl text-xs text-muted-foreground md:text-sm print:text-xs">
                    Se certifica que la vulnerabilidad descrita fue reportada de forma responsable, validada por la moderación
                    de la plataforma y corregida por la organización afectada, que cerró el informe como resuelto.
                </p>
                <div class="mt-2 rounded border border-primary/30 bg-background px-3 py-1 font-mono text-xs font-bold tracking-widest text-foreground">
                    ID OFICIAL: <span class="text-primary">{certificado.codigo}</span>
                </div>
            </header>

            <!-- Datos del hallazgo -->
            <div class="my-8 grid gap-6 text-sm md:grid-cols-2 print:my-5 print:grid-cols-2 print:gap-4">
                <section class="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-5 print:space-y-3 print:p-4">
                    <h3 class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-primary uppercase">
                        <Lock class="size-3.5" />
                        Detalles de la vulnerabilidad
                    </h3>

                    <div>
                        <span class="block text-xs text-muted-foreground">Número de informe y título</span>
                        <span class="font-semibold">{certificado.datos.numero_reporte} — {certificado.datos.titulo}</span>
                    </div>

                    <div>
                        <span class="block text-xs text-muted-foreground">Categoría</span>
                        <span class="font-medium">{certificado.datos.categoria ?? 'Seguridad de aplicación'}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <span class="mb-1 block text-xs text-muted-foreground">Severidad</span>
                            <SeverityBadge severidad={certificado.datos.severidad as Severidad} />
                        </div>
                        <div>
                            <span class="mb-1 block text-xs text-muted-foreground">Puntuación CVSS v3.1</span>
                            <Badge variant="outline" class="bg-background font-mono text-sm font-bold">
                                {Number(certificado.datos.cvss_score).toFixed(1)} / 10.0
                            </Badge>
                        </div>
                    </div>

                    {#if certificado.datos.cvss_vector}
                        <div>
                            <span class="block text-xs text-muted-foreground">Vector CVSS</span>
                            <code class="font-mono text-[11px] break-all">{certificado.datos.cvss_vector}</code>
                        </div>
                    {/if}
                </section>

                <section class="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-5 print:space-y-3 print:p-4">
                    <h3 class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-primary uppercase">
                        <Award class="size-3.5" />
                        Atribución y trazabilidad
                    </h3>

                    <div>
                        <span class="block text-xs text-muted-foreground">Investigador</span>
                        <span class="font-semibold">{certificado.datos.investigador_alias}</span>
                    </div>

                    <div>
                        <span class="block text-xs text-muted-foreground">Organización afectada</span>
                        <span class="font-medium">{certificado.datos.empresa_nombre}</span>
                    </div>

                    <div>
                        <span class="block text-xs text-muted-foreground">Programa</span>
                        <span class="font-medium">{certificado.datos.programa_nombre}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-t border-border/40 pt-3">
                        <div>
                            <span class="block text-[11px] text-muted-foreground">Reportado el</span>
                            <span class="font-mono text-xs">{formatearFecha(certificado.datos.fecha_reporte)}</span>
                        </div>
                        <div>
                            <span class="block text-[11px] text-muted-foreground">Resuelto el</span>
                            <span class="font-mono text-xs font-medium text-primary">{formatearFecha(certificado.datos.fecha_resolucion)}</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Sello criptográfico -->
            <section class="space-y-3 rounded-xl border border-primary/30 bg-muted/40 p-5 print:p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Key class="size-4 text-primary" />
                        <h4 class="text-xs font-bold tracking-wider uppercase">Sello criptográfico de integridad</h4>
                    </div>
                    <Badge variant="outline" class="border-primary/40 font-mono text-[10px] text-primary uppercase">SHA-256 + PGP</Badge>
                </div>

                <div>
                    <span class="mb-0.5 block text-[11px] text-muted-foreground">Huella digital del registro (SHA-256)</span>
                    <div class="rounded border border-border/60 bg-background p-2 font-mono text-xs font-semibold break-all text-foreground select-all">
                        {huellaAgrupada}
                    </div>
                </div>

                {#if certificado.clave_huella}
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
                        <span class="text-muted-foreground">Huella de la clave PGP de la plataforma</span>
                        <span class="font-mono font-medium break-all">{certificado.clave_huella}</span>
                    </div>
                {/if}

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs">
                    <span class="text-muted-foreground">Verificación pública</span>
                    <span class="font-mono font-medium break-all">{urlVerificacion}</span>
                </div>

                <div class="pt-1 print:hidden">
                    <button
                        type="button"
                        class="flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        onclick={() => (mostrandoFirma = !mostrandoFirma)}
                    >
                        {mostrandoFirma ? 'Ocultar firma digital PGP ▲' : 'Ver firma digital PGP (ASCII-armored) ▼'}
                    </button>

                    {#if mostrandoFirma}
                        <pre class="mt-2 overflow-x-auto rounded border border-border/60 bg-background p-3 font-mono text-[10px] leading-tight text-muted-foreground">{certificado.firma_pgp}</pre>
                    {/if}
                </div>
            </section>

            <!-- Pie -->
            <footer class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-border/80 pt-6 text-xs text-muted-foreground md:flex-row print:mt-5 print:flex-row print:pt-4">
                <div class="text-center md:text-left print:text-left">
                    <p class="font-semibold text-foreground">Huella · Plataforma de divulgación coordinada de vulnerabilidades</p>
                    <p class="text-[11px]">Integridad SHA-256 (FIPS 180-4) y firma OpenPGP (RFC 4880).</p>
                </div>
                <div class="text-center font-mono text-[11px] md:text-right print:text-right">
                    Emitido: {formatearFecha(certificado.emitido_en)}
                </div>
            </footer>
        </article>
    </div>
</div>

<style>
    @media print {
        @page {
            size: A4;
            margin: 12mm;
        }

        /* En papel siempre en claro, sea cual sea el tema: se redefinen los tokens dentro del documento. */
        :global(html),
        :global(body) {
            background: white !important;
        }

        .certificado {
            --background: hsl(0 0% 100%);
            --foreground: hsl(240 6% 10%);
            --card: hsl(0 0% 100%);
            --card-foreground: hsl(240 6% 10%);
            --muted: hsl(240 5% 96%);
            --muted-foreground: hsl(240 4% 38%);
            --border: hsl(240 5% 82%);
            --primary: hsl(152 76% 26%);
            --primary-foreground: hsl(0 0% 100%);
            color: var(--foreground);
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
            break-inside: avoid;
        }
    }
</style>
