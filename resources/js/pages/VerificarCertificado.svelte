<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import SitioCabecera from '@/components/publico/SitioCabecera.svelte';
    import SitioPie from '@/components/publico/SitioPie.svelte';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import XCircle from '@lucide/svelte/icons/x-circle';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import Key from '@lucide/svelte/icons/key';
    import Download from '@lucide/svelte/icons/download';
    import Terminal from '@lucide/svelte/icons/terminal';
    import type { Severidad } from '@/types/enums';

    let {
        existe,
        codigo,
        verificacion = null,
        certificado = null,
        descargas = null,
    }: {
        existe: boolean;
        codigo: string;
        verificacion?: {
            valido: boolean;
            hash_valido: boolean;
            firma_valida: boolean;
            vigente: boolean;
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
                severidad: string | null;
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
        descargas?: { firma: string; clave: string | null } | null;
    } = $props();

    // Tres desenlaces: auténtico y vigente, auténtico pero ya no vigente, o alterado.
    const estado = $derived(!verificacion ? null : !verificacion.valido ? 'alterado' : verificacion.vigente ? 'vigente' : 'no_vigente');

    function formatearFecha(iso?: string | null): string {
        if (!iso) return 'N/A';
        try {
            return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
        } catch {
            return iso;
        }
    }
</script>

<AppHead title={`Verificar certificado ${codigo}`} />

<div class="flex min-h-screen flex-col bg-background text-foreground">
    <SitioCabecera />

    <main class="mx-auto w-full max-w-3xl flex-1 space-y-6 px-4 py-10 sm:px-6">
        <div>
            <p class="text-xs font-semibold tracking-widest text-primary uppercase">Verificador de certificados</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">Comprueba un certificado de divulgación</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Huella recalcula la huella SHA-256 del certificado y comprueba su firma PGP en el momento de tu consulta.
            </p>
        </div>

        {#if existe && certificado && verificacion}
            <div
                class="space-y-6 rounded-2xl border-2 p-6 shadow-xl md:p-8 {estado === 'vigente'
                    ? 'border-primary/50 bg-card'
                    : estado === 'no_vigente'
                      ? 'border-chart-4/50 bg-card'
                      : 'border-destructive/50 bg-destructive/5'}"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="shrink-0 rounded-full p-3 {estado === 'vigente'
                            ? 'bg-primary/10 text-primary'
                            : estado === 'no_vigente'
                              ? 'bg-chart-4/10 text-chart-4'
                              : 'bg-destructive/10 text-destructive'}"
                    >
                        {#if estado === 'vigente'}
                            <CheckCircle2 class="size-8" />
                        {:else if estado === 'no_vigente'}
                            <TriangleAlert class="size-8" />
                        {:else}
                            <ShieldAlert class="size-8" />
                        {/if}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold md:text-2xl">
                                {#if estado === 'vigente'}
                                    Certificado auténtico y vigente
                                {:else if estado === 'no_vigente'}
                                    Certificado auténtico, pero ya no vigente
                                {:else}
                                    Alerta: el certificado fue alterado
                                {/if}
                            </h2>
                            <Badge variant={estado === 'alterado' ? 'destructive' : 'outline'} class="font-mono text-xs">
                                {estado === 'vigente' ? 'VERIFICADO' : estado === 'no_vigente' ? 'NO VIGENTE' : 'CORRUPTO'}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {#if estado === 'vigente'}
                                La firma PGP y la huella SHA-256 coinciden, y el informe sigue cerrado como resuelto.
                            {:else if estado === 'no_vigente'}
                                Lo emitió Huella y no fue modificado, pero el informe al que se refiere ya no está cerrado como resuelto o fue retirado.
                            {:else}
                                La huella recalculada no coincide con el registro original o la firma digital no es válida.
                            {/if}
                        </p>
                    </div>
                </div>

                <!-- Comprobaciones -->
                <div class="grid gap-3 rounded-xl border border-border/50 bg-muted/30 p-4 text-xs sm:grid-cols-3">
                    {#each [
                        { ok: verificacion.hash_valido, bien: 'Integridad SHA-256: intacta', mal: 'Integridad SHA-256: alterada' },
                        { ok: verificacion.firma_valida, bien: 'Firma OpenPGP: legítima', mal: 'Firma OpenPGP: no verificable' },
                        { ok: verificacion.vigente, bien: 'Informe: cerrado como resuelto', mal: 'Informe: ya no vigente' },
                    ] as c (c.bien)}
                        <div class="flex items-center gap-2">
                            {#if c.ok}
                                <CheckCircle2 class="size-4 shrink-0 text-primary" />
                                <span>{c.bien}</span>
                            {:else}
                                <XCircle class="size-4 shrink-0 text-destructive" />
                                <span class="font-semibold text-destructive">{c.mal}</span>
                            {/if}
                        </div>
                    {/each}
                </div>

                <!-- Datos certificados -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold tracking-wider text-muted-foreground uppercase">Datos certificados</h3>

                    <dl class="grid gap-4 border-t border-border pt-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Código oficial</dt>
                            <dd class="font-mono font-bold text-primary">{certificado.codigo}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Informe</dt>
                            <dd class="font-semibold">{certificado.datos.numero_reporte} — {certificado.datos.titulo}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Organización</dt>
                            <dd class="font-medium">{certificado.datos.empresa_nombre}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Programa</dt>
                            <dd class="font-medium">{certificado.datos.programa_nombre}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Investigador</dt>
                            <dd class="font-medium">{certificado.datos.investigador_alias}</dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-xs text-muted-foreground">Severidad y puntuación</dt>
                            <dd class="flex items-center gap-2">
                                {#if certificado.datos.severidad}<SeverityBadge severidad={certificado.datos.severidad as Severidad} />{:else}<span class="text-xs text-muted-foreground">Sin clasificar</span>{/if}
                                <span class="font-mono text-xs font-bold">CVSS {certificado.datos.severidad ? Number(certificado.datos.cvss_score).toFixed(1) : '—'}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Resuelto el</dt>
                            <dd class="font-medium">{formatearFecha(certificado.datos.fecha_resolucion)}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Certificado emitido el</dt>
                            <dd class="font-medium">{formatearFecha(certificado.emitido_en)}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Prueba criptográfica -->
                <div class="space-y-2 rounded-lg border border-border bg-background p-4 text-xs">
                    <div class="flex items-center gap-1.5 font-bold">
                        <Key class="size-3.5 text-primary" />
                        <span>Prueba criptográfica</span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-muted-foreground">Huella SHA-256 comprobada</span>
                        <div class="font-mono text-[11px] break-all text-primary">{verificacion.huella}</div>
                    </div>
                    {#if verificacion.clave_huella}
                        <div class="pt-1">
                            <span class="block text-[11px] text-muted-foreground">Huella de la clave PGP de la plataforma</span>
                            <div class="font-mono text-[11px] break-all">{verificacion.clave_huella}</div>
                        </div>
                    {/if}
                </div>

                <!-- Verificación independiente -->
                {#if descargas}
                    <div class="space-y-3 rounded-lg border border-border bg-muted/20 p-4 text-xs">
                        <div class="flex items-center gap-1.5 font-bold">
                            <Terminal class="size-3.5 text-primary" />
                            <span>Verifícalo por tu cuenta</span>
                        </div>
                        <p class="text-muted-foreground">
                            No hace falta fiarse de esta página: descarga la firma y la clave pública y compruébalas con GnuPG.
                            Lo firmado es la huella SHA-256 tal cual, sin salto de línea.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" href={descargas.firma} download>
                                <Download class="mr-1.5 size-4" />
                                Firma (.asc)
                            </Button>
                            {#if descargas.clave}
                                <Button variant="outline" size="sm" href={descargas.clave} download>
                                    <Download class="mr-1.5 size-4" />
                                    Clave pública (.asc)
                                </Button>
                            {/if}
                        </div>
                        <pre class="overflow-x-auto rounded border border-border/60 bg-background p-3 font-mono text-[11px] leading-relaxed">gpg --import huella-*.asc
printf '%s' {certificado.huella} &gt; huella.txt
gpg --verify {certificado.codigo}.sig.asc huella.txt</pre>
                    </div>
                {/if}
            </div>
        {:else}
            <div class="space-y-4 rounded-2xl border-2 border-destructive/50 bg-card p-8 text-center shadow-xl">
                <div class="mx-auto w-fit rounded-full bg-destructive/10 p-4 text-destructive">
                    <ShieldAlert class="size-12" />
                </div>
                <h2 class="text-2xl font-bold">Certificado no encontrado</h2>
                <p class="mx-auto max-w-md text-sm text-muted-foreground">
                    El código <span class="rounded bg-muted px-2 py-0.5 font-mono font-semibold text-foreground">{codigo}</span> no corresponde
                    a ningún certificado emitido por Huella. Revisa que esté bien escrito: si te lo presentaron como válido, no lo es.
                </p>
                <div class="pt-4">
                    <Button href="/">Volver a Huella</Button>
                </div>
            </div>
        {/if}
    </main>

    <SitioPie />
</div>
