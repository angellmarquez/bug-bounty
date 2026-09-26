<script module lang="ts">
    export const layout = {
        title: 'Verificación de Certificado',
        description: 'Verificador público de integridad criptográfica y firma digital.',
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import Key from '@lucide/svelte/icons/key';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import type { Severidad } from '@/types/enums';

    let {
        existe,
        codigo,
        verificacion = null,
        certificado = null,
    }: {
        existe: boolean;
        codigo: string;
        verificacion?: {
            valido: boolean;
            hash_valido: boolean;
            firma_valida: boolean;
            huella: string;
            huella_calculada: string;
            clave_huella: string | null;
        } | null;
        certificado?: {
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
        } | null;
    } = $props();

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

<AppHead title={`Verificación de Certificado ${codigo}`} />

<div class="min-h-screen bg-background text-foreground flex flex-col justify-between p-4 md:p-8">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <!-- Logo / Marca superior -->
        <div class="flex items-center justify-between border-b border-border pb-4">
            <div class="flex items-center gap-2">
                <ShieldCheck class="size-6 text-primary" />
                <span class="font-bold tracking-tight text-lg">Bug Bounty Platform</span>
                <span class="text-xs text-muted-foreground hidden sm:inline">· Verificador de Confianza Criptográfica</span>
            </div>
            <Button variant="ghost" size="sm" href="/">
                <ArrowLeft class="mr-1.5 size-4" />
                Inicio
            </Button>
        </div>

        {#if existe && certificado && verificacion}
            <!-- RESULTADO POSITIVO -->
            <div class="rounded-2xl border-2 {verificacion.valido ? 'border-primary/50 bg-card' : 'border-destructive/50 bg-destructive/5'} p-6 md:p-8 shadow-xl space-y-6">
                
                <div class="flex items-start gap-4">
                    <div class="rounded-full {verificacion.valido ? 'bg-primary/10 text-primary' : 'bg-destructive/10 text-destructive'} p-3 shrink-0">
                        {#if verificacion.valido}
                            <CheckCircle2 class="size-8" />
                        {:else}
                            <ShieldAlert class="size-8" />
                        {/if}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl md:text-2xl font-bold">
                                {verificacion.valido ? 'Certificado Auténtico y Vigente' : 'Alerta: Alteración de Integridad Detectada'}
                            </h2>
                            <Badge variant={verificacion.valido ? 'default' : 'destructive'} class="font-mono text-xs">
                                {verificacion.valido ? 'VERIFICADO' : 'CORRUPTO'}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {verificacion.valido
                                ? 'La firma digital PGP y la huella SHA-256 de este certificado fueron recalculadas e inspeccionadas en tiempo real.'
                                : 'La huella calculada no coincide con el registro original o la firma digital no es válida.'}
                        </p>
                    </div>
                </div>

                <!-- Lista de comprobaciones criptográficas -->
                <div class="grid gap-3 sm:grid-cols-2 rounded-xl bg-muted/30 p-4 border border-border/50 text-xs">
                    <div class="flex items-center gap-2">
                        {#if verificacion.hash_valido}
                            <CheckCircle2 class="size-4 text-primary shrink-0" />
                            <span>Integridad SHA-256: <strong>Intacta</strong></span>
                        {:else}
                            <XCircle class="size-4 text-destructive shrink-0" />
                            <span class="text-destructive font-semibold">Integridad SHA-256: Alterada</span>
                        {/if}
                    </div>

                    <div class="flex items-center gap-2">
                        {#if verificacion.firma_valida}
                            <CheckCircle2 class="size-4 text-primary shrink-0" />
                            <span>Firma digital OpenPGP: <strong>Legítima</strong></span>
                        {:else}
                            <XCircle class="size-4 text-destructive shrink-0" />
                            <span class="text-destructive font-semibold">Firma OpenPGP: No verificable</span>
                        {/if}
                    </div>
                </div>

                <!-- Ficha del Hallazgo Auditado -->
                <div class="space-y-4 pt-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                        Datos del Hallazgo de Seguridad Auditado
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2 text-sm border-t border-border pt-4">
                        <div>
                            <span class="text-xs text-muted-foreground block">Código oficial</span>
                            <span class="font-mono font-bold text-primary">{certificado.codigo}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Informe auditado</span>
                            <span class="font-semibold">{certificado.datos.numero_reporte} — {certificado.datos.titulo}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Organización / Empresa</span>
                            <span class="font-medium">{certificado.datos.empresa_nombre}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Programa</span>
                            <span class="font-medium">{certificado.datos.programa_nombre}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Investigador Ético</span>
                            <span class="font-medium">{certificado.datos.investigador_alias}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block mb-1">Severidad y Puntuación</span>
                            <div class="flex items-center gap-2">
                                <SeverityBadge severidad={certificado.datos.severidad as Severidad} />
                                <span class="font-mono text-xs font-bold">CVSS {Number(certificado.datos.cvss_score).toFixed(1)}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Fecha de resolución y mitigación</span>
                            <span class="font-medium">{formatearFecha(certificado.datos.fecha_resolucion)}</span>
                        </div>

                        <div>
                            <span class="text-xs text-muted-foreground block">Certificado emitido el</span>
                            <span class="font-medium">{formatearFecha(certificado.emitido_en)}</span>
                        </div>
                    </div>
                </div>

                <!-- Detalle Criptográfico Monospace -->
                <div class="rounded-lg border border-border bg-background p-4 space-y-2 text-xs">
                    <div class="flex items-center gap-1.5 font-bold text-foreground">
                        <Key class="size-3.5 text-primary" />
                        <span>Prueba Criptográfica Canónica</span>
                    </div>

                    <div>
                        <span class="text-[11px] text-muted-foreground block">Huella SHA-256 Comprobada:</span>
                        <div class="font-mono text-[11px] break-all text-primary">{verificacion.huella}</div>
                    </div>

                    {#if verificacion.clave_huella}
                        <div class="pt-1">
                            <span class="text-[11px] text-muted-foreground block">Huella Clave PGP Plataforma:</span>
                            <div class="font-mono text-[11px] break-all">{verificacion.clave_huella}</div>
                        </div>
                    {/if}
                </div>

            </div>
        {:else}
            <!-- RESULTADO NEGATIVO: NO EXISTE -->
            <div class="rounded-2xl border-2 border-destructive/50 bg-card p-8 shadow-xl text-center space-y-4">
                <div class="mx-auto rounded-full bg-destructive/10 text-destructive p-4 w-fit">
                    <ShieldAlert class="size-12" />
                </div>
                <h2 class="text-2xl font-bold">Certificado No Encontrado o Inválido</h2>
                <p class="text-sm text-muted-foreground max-w-md mx-auto">
                    El código <span class="font-mono font-semibold text-foreground bg-muted px-2 py-0.5 rounded">{codigo}</span> no corresponde
                    a ningún certificado emitido por esta plataforma. Podría haber sido alterado, revocado o pertenecer a otro entorno.
                </p>
                <div class="pt-4">
                    <Button href="/">Volver a la plataforma</Button>
                </div>
            </div>
        {/if}

        <p class="text-center text-xs text-muted-foreground pt-4">
            Plataforma de Divulgación Coordinada de Vulnerabilidades · Módulo de Verificación Criptográfica
        </p>
    </div>
</div>
