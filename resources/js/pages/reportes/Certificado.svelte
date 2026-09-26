<script module lang="ts">
    export const layout = {
        title: 'Certificado de Divulgación Responsable',
        description: 'Certificado oficial con firma criptográfica PGP y huella de integridad SHA-256.',
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
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

    function imprimir() {
        window.print();
    }

    function formatearFecha(iso?: string | null): string {
        if (!iso) return 'N/A';
        try {
            return new Intl.DateTimeFormat('es-ES', {
                dateStyle: 'long',
                timeStyle: 'short',
            }).format(new Date(iso));
        } catch {
            return iso;
        }
    }
</script>

<AppHead title={`Certificado ${certificado.codigo}`} />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <!-- Barra de acciones (oculta al imprimir) -->
    <div class="print:hidden flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4">
        <div class="flex items-center gap-3">
            <BotonVolver href={`/reportes/${reporteId}`} etiqueta="Volver al informe" />
            <div>
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <Award class="size-5 text-primary" />
                    Certificado Oficial de Hallazgo
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

            <Button size="sm" onclick={imprimir} class="bg-primary text-primary-foreground font-semibold">
                <Printer class="mr-1.5 size-4" />
                Imprimir / Guardar PDF
            </Button>
        </div>
    </div>

    <!-- DOCUMENTO DEL CERTIFICADO (Estilo oficial de Seguridad Ofensiva) -->
    <div class="mx-auto w-full max-w-4xl">
        <div class="relative overflow-hidden rounded-2xl border-2 border-primary/40 bg-card p-8 md:p-12 shadow-2xl print:border-2 print:border-black print:bg-white print:p-8 print:text-black print:shadow-none">
            
            <!-- Sello decorativo de fondo -->
            <div class="pointer-events-none absolute -right-16 -top-16 opacity-5 print:opacity-10">
                <ShieldCheck class="size-80 text-primary print:text-black" />
            </div>

            <!-- Encabezado del Certificado -->
            <div class="flex flex-col items-center text-center gap-2 border-b border-border/80 pb-6 print:border-black">
                <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary print:border print:border-black print:text-black">
                    <ShieldCheck class="size-4" />
                    Divulgación Coordinada de Vulnerabilidades
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-foreground print:text-black mt-2">
                    CERTIFICADO OFICIAL DE MITIGACIÓN ÉTICA
                </h2>
                <p class="text-xs md:text-sm text-muted-foreground print:text-gray-700 max-w-xl">
                    Se certifica que la vulnerabilidad de seguridad descrita en este documento fue reportada responsablemente,
                    validada técnicamente y mitigada conforme a las directrices de seguridad de la plataforma.
                </p>
                <div class="mt-2 font-mono text-xs text-primary font-bold tracking-widest bg-muted/60 px-3 py-1 rounded border border-primary/20 print:border-black">
                    ID OFICIAL: {certificado.codigo}
                </div>
            </div>

            <!-- Cuerpo de Datos del Hallazgo -->
            <div class="my-8 grid gap-6 md:grid-cols-2 text-sm">
                <!-- Columna Izquierda: Informe y Hallazgo -->
                <div class="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-5 print:border-gray-300 print:bg-transparent">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary print:text-black flex items-center gap-1.5">
                        <Lock class="size-3.5" />
                        Detalles de la Vulnerabilidad
                    </h3>

                    <div>
                        <span class="text-xs text-muted-foreground print:text-gray-600 block">Número de informe y título</span>
                        <span class="font-semibold text-foreground print:text-black">{certificado.datos.numero_reporte} — {certificado.datos.titulo}</span>
                    </div>

                    <div>
                        <span class="text-xs text-muted-foreground print:text-gray-600 block">Categoría de seguridad</span>
                        <span class="font-medium text-foreground print:text-black">{certificado.datos.categoria ?? 'Seguridad de Aplicación'}</span>
                    </div>

                    <div class="flex items-center gap-4">
                        <div>
                            <span class="text-xs text-muted-foreground print:text-gray-600 block mb-1">Severidad</span>
                            <SeverityBadge severidad={certificado.datos.severidad as Severidad} />
                        </div>
                        <div>
                            <span class="text-xs text-muted-foreground print:text-gray-600 block mb-1">Puntuación CVSS v3.1</span>
                            <Badge variant="outline" class="font-mono font-bold text-sm bg-background print:border-black">
                                {Number(certificado.datos.cvss_score).toFixed(1)} / 10.0
                            </Badge>
                        </div>
                    </div>

                    {#if certificado.datos.cvss_vector}
                        <div>
                            <span class="text-xs text-muted-foreground print:text-gray-600 block">Vector CVSS Oficial</span>
                            <code class="text-[11px] font-mono break-all text-primary/90 print:text-black">{certificado.datos.cvss_vector}</code>
                        </div>
                    {/if}
                </div>

                <!-- Columna Derecha: Autoridad y Fechas -->
                <div class="space-y-4 rounded-xl border border-border/60 bg-muted/20 p-5 print:border-gray-300 print:bg-transparent">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary print:text-black flex items-center gap-1.5">
                        <Award class="size-3.5" />
                        Atribución y Trazabilidad
                    </h3>

                    <div>
                        <span class="text-xs text-muted-foreground print:text-gray-600 block">Investigador Ético</span>
                        <span class="font-semibold text-foreground print:text-black">{certificado.datos.investigador_alias}</span>
                    </div>

                    <div>
                        <span class="text-xs text-muted-foreground print:text-gray-600 block">Organización Afectada</span>
                        <span class="font-medium text-foreground print:text-black">{certificado.datos.empresa_nombre}</span>
                    </div>

                    <div>
                        <span class="text-xs text-muted-foreground print:text-gray-600 block">Programa de Divulgación</span>
                        <span class="font-medium text-foreground print:text-black">{certificado.datos.programa_nombre}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border/40 print:border-gray-300">
                        <div>
                            <span class="text-[11px] text-muted-foreground print:text-gray-600 block">Reportado el</span>
                            <span class="text-xs font-mono">{formatearFecha(certificado.datos.fecha_reporte)}</span>
                        </div>
                        <div>
                            <span class="text-[11px] text-muted-foreground print:text-gray-600 block">Mitigado el</span>
                            <span class="text-xs font-mono font-medium text-primary print:text-black">{formatearFecha(certificado.datos.fecha_resolucion)}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloque Criptográfico: Huella SHA-256 y Firma PGP -->
            <div class="rounded-xl border border-primary/30 bg-muted/40 p-5 space-y-3 print:border-black print:bg-transparent">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Key class="size-4 text-primary print:text-black" />
                        <h4 class="text-xs font-bold uppercase tracking-wider text-foreground print:text-black">
                            Sello Criptográfico de Integridad Inmutable
                        </h4>
                    </div>
                    <Badge variant="outline" class="text-[10px] uppercase font-mono border-primary/40 text-primary print:border-black print:text-black">
                        SHA-256 + PGP
                    </Badge>
                </div>

                <div>
                    <span class="text-[11px] text-muted-foreground print:text-gray-600 block mb-0.5">Huella Digital del Registro (SHA-256)</span>
                    <div class="font-mono text-xs break-all bg-background/80 p-2 rounded border border-border/60 text-primary font-semibold select-all print:border-black print:text-black">
                        {certificado.huella}
                    </div>
                </div>

                {#if certificado.clave_huella}
                    <div class="flex items-center justify-between text-xs pt-1">
                        <span class="text-muted-foreground print:text-gray-600">Huella Clave PGP de Custodia:</span>
                        <span class="font-mono font-medium">{certificado.clave_huella}</span>
                    </div>
                {/if}

                <!-- Detalle de Firma PGP desplegable -->
                <div class="pt-2">
                    <button
                        type="button"
                        class="print:hidden text-xs text-primary hover:underline flex items-center gap-1 font-medium"
                        onclick={() => mostrandoFirma = !mostrandoFirma}
                    >
                        {mostrandoFirma ? 'Ocultar bloque de firma digital PGP ▲' : 'Ver firma digital ASCII-armored PGP ▼'}
                    </button>

                    {#if mostrandoFirma}
                        <pre class="mt-2 text-[10px] font-mono leading-tight bg-background/90 p-3 rounded border border-border/60 overflow-x-auto text-muted-foreground print:text-black">{certificado.firma_pgp}</pre>
                    {/if}
                </div>
            </div>

            <!-- Pie de Certificado -->
            <div class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 border-t border-border/80 pt-6 text-xs text-muted-foreground print:border-black print:text-gray-700">
                <div class="text-center md:text-left">
                    <p class="font-semibold text-foreground print:text-black">Plataforma de Divulgación Coordinada de Vulnerabilidades</p>
                    <p class="text-[11px]">Validación matemática bajo estándares RFC 4880 (OpenPGP) y FIPS PUB 180-4 (SHA-256).</p>
                </div>

                <div class="text-center md:text-right font-mono text-[11px]">
                    <span>Emitido: {formatearFecha(certificado.emitido_en)}</span>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    @media print {
        :global(body) {
            background: white !important;
            color: black !important;
        }
        :global(aside), :global(nav), :global(header) {
            display: none !important;
        }
    }
</style>
