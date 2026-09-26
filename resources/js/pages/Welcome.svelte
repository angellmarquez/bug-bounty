<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import BadgeCheck from '@lucide/svelte/icons/badge-check';
    import Building2 from '@lucide/svelte/icons/building-2';
    import Bug from '@lucide/svelte/icons/bug';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import EyeOff from '@lucide/svelte/icons/eye-off';
    import FileLock2 from '@lucide/svelte/icons/file-lock-2';
    import Link2 from '@lucide/svelte/icons/link-2';
    import ListChecks from '@lucide/svelte/icons/list-checks';
    import Medal from '@lucide/svelte/icons/medal';
    import Radar from '@lucide/svelte/icons/radar';
    import ScrollText from '@lucide/svelte/icons/scroll-text';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Trophy from '@lucide/svelte/icons/trophy';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import NivelAccesoBadge from '@/components/NivelAccesoBadge.svelte';
    import SitioCabecera from '@/components/publico/SitioCabecera.svelte';
    import SitioPie from '@/components/publico/SitioPie.svelte';
    import RangoBadge from '@/components/RangoBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { aparecer, contador, prefiereMenosMovimiento } from '@/lib/animaciones';
    import type { NivelAcceso } from '@/lib/rangos';

    type ProgramaPublico = { id: number; nombre: string; empresa: string | null; nivel_acceso: NivelAcceso | null; tipos: string[] };
    type Lider = { id: number; name: string; puntos: number };

    let {
        cifras = { programas: 0, investigadores: 0, resueltas: 0 },
        programas = [],
        lideres = [],
    }: {
        cifras?: { programas: number; investigadores: number; resueltas: number };
        programas?: ProgramaPublico[];
        lideres?: Lider[];
    } = $props();

    const usuario = $derived(page.props.auth?.user);

    // --- Terminal de la portada: se "escribe" línea a línea. ---
    const lineas = [
        { tipo: 'cmd', texto: 'huella reportar --programa acme --adjuntar captura.png' },
        { tipo: 'ok', texto: 'PoC validada contra el formulario del programa' },
        { tipo: 'ok', texto: 'Foto limpia de metadatos (EXIF/GPS eliminados)' },
        { tipo: 'ok', texto: 'Cifrado PGP · huella 7F3A 91C0 … C21E' },
        { tipo: 'ok', texto: 'Enviado a triaje ciego' },
        { tipo: 'estado', texto: 'Estado: en revisión · CVSS 8.1 (alta)' },
    ] as const;

    let escritas = $state(0);
    let caracteres = $state(0);

    onMount(() => {
        if (prefiereMenosMovimiento()) {
            escritas = lineas.length;

            return;
        }

        let temporizador: ReturnType<typeof setTimeout>;
        const avanzar = () => {
            const actual = lineas[escritas];
            if (!actual) return;
            if (actual.tipo === 'cmd' && caracteres < actual.texto.length) {
                caracteres++;
                temporizador = setTimeout(avanzar, 28);

                return;
            }
            escritas++;
            caracteres = 0;
            temporizador = setTimeout(avanzar, escritas === 1 ? 350 : 420);
        };
        temporizador = setTimeout(avanzar, 500);

        return () => clearTimeout(temporizador);
    });

    // --- Cómo funciona ---
    let publico = $state<'investigadores' | 'empresas'>('investigadores');
    const pasos = {
        investigadores: [
            { icono: Radar, titulo: 'Elige un programa', texto: 'Explora los programas públicos o los privados a los que te invitan. Tu rango abre los de mayor nivel.' },
            { icono: Bug, titulo: 'Reporta con evidencia', texto: 'Cada programa trae su formulario de prueba de concepto, calculadora CVSS y fotos cifradas.' },
            { icono: Trophy, titulo: 'Deja huella', texto: 'Los hallazgos válidos suman puntos y rango. Los falsos positivos restan, siempre con derecho a apelar.' },
        ],
        empresas: [
            { icono: Building2, titulo: 'Registra tu empresa', texto: 'Verificamos la solicitud antes de darte acceso: nadie publica en nombre de otro.' },
            { icono: ListChecks, titulo: 'Publica tu programa', texto: 'Define el alcance, qué fallos buscas y la evidencia que pides. Público o solo por invitación.' },
            { icono: BadgeCheck, titulo: 'Recibe informes útiles', texto: 'La moderación descarta el ruido. Tú lees los informes validados, cifrados con la clave de tu empresa.' },
        ],
    };

    const pilares = [
        { icono: FileLock2, titulo: 'Cifrado PGP de principio a fin', texto: 'Descripciones, pruebas de concepto y fotos se guardan cifradas. Ni la base de datos las ve en claro.' },
        { icono: ShieldCheck, titulo: 'Permisos por atributos (ABAC)', texto: 'Cada acción se evalúa con reglas sobre quién eres, qué pides y en qué estado está. Nada de accesos por defecto.' },
        { icono: EyeOff, titulo: 'Triaje ciego', texto: 'Quien modera no ve quién reportó: juzga el hallazgo, no a la persona. Sin favoritismos.' },
        { icono: Link2, titulo: 'Trazabilidad verificable', texto: 'Cada paso de una apelación queda encadenado con huellas SHA-256: si alguien lo altera, se nota.' },
    ];

    const preguntas = [
        { p: '¿Quién puede reportar vulnerabilidades?', r: 'Cualquier persona que cree una cuenta de investigador y acepte los términos. Los programas de mayor nivel exigen un rango mínimo, que se gana con hallazgos válidos.' },
        { p: '¿Cómo se protege mi hallazgo?', r: 'Se cifra con PGP antes de guardarse y solo lo descifran quienes deben leerlo: la moderación asignada y, cuando se valida, la empresa. Cada lectura queda registrada.' },
        { p: '¿Qué pasa si mi informe no es válido?', r: 'Se rechaza con un motivo. Si parece fabricado para inflar métricas puede haber una sanción proporcional, que puedes apelar dentro del plazo ante el administrador.' },
        { p: '¿Cómo empieza una empresa?', r: 'Registra la empresa con su identificador fiscal. Tras la verificación puede publicar programas públicos o privados e invitar investigadores a estos últimos.' },
        { p: '¿Qué es la divulgación coordinada?', r: 'Un acuerdo para que la vulnerabilidad se corrija antes de hacerse pública. Nuestra Política de Divulgación fija los plazos y protege a quien reporta de buena fe.' },
    ];
</script>

<AppHead title="Divulgación coordinada de vulnerabilidades" />

<div class="min-h-screen bg-background text-foreground">
    <SitioCabecera enInicio />

    <main>
        <!-- Portada -->
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute -top-24 right-[-10%] size-[520px] text-primary/[0.06]" aria-hidden="true">
                <AppLogoIcon class="size-full" />
            </div>
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-16 pb-20 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:pt-24">
                <div use:aparecer>
                    <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-3 py-1 text-xs text-muted-foreground">
                        <span class="size-1.5 rounded-full bg-primary"></span>
                        Divulgación coordinada · cifrado PGP
                    </p>
                    <h1 class="text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl lg:text-6xl">
                        Cada vulnerabilidad<br /><span class="text-primary">deja huella.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
                        Huella conecta a investigadores de seguridad con empresas que quieren saber dónde fallan antes que
                        los atacantes. Informes cifrados, triaje ciego y un historial que nadie puede reescribir.
                    </p>

                    {#if usuario}
                        <div class="mt-8">
                            <Button href="/dashboard" size="lg">Ir a mi panel <ArrowRight class="ml-1 size-4" /></Button>
                        </div>
                    {:else}
                        <div class="mt-8 grid max-w-xl gap-3 sm:grid-cols-2">
                            <Link href="/register" class="group rounded-xl border border-primary/40 bg-primary/10 p-4 transition-colors hover:bg-primary/15" data-test="cta-investigador">
                                <span class="flex items-center gap-2 font-medium"><UserPlus class="size-4 text-primary" /> Soy investigador</span>
                                <span class="mt-1 block text-xs text-muted-foreground">Encuentra fallos, repórtalos y sube de rango.</span>
                            </Link>
                            <Link href="/empresa/registro" class="group rounded-xl border border-border bg-card/60 p-4 transition-colors hover:border-primary/40" data-test="cta-empresa">
                                <span class="flex items-center gap-2 font-medium"><Building2 class="size-4 text-primary" /> Soy empresa</span>
                                <span class="mt-1 block text-xs text-muted-foreground">Publica tu programa y recibe informes validados.</span>
                            </Link>
                        </div>
                        <p class="mt-4 text-xs text-muted-foreground">
                            ¿Ya tienes cuenta?
                            <Link href="/login" class="text-foreground underline underline-offset-4">Acceso investigadores</Link>
                            ·
                            <Link href="/empresa/login" class="text-foreground underline underline-offset-4">Acceso empresas</Link>
                        </p>
                    {/if}
                </div>

                <!-- Terminal animada -->
                <div use:aparecer={120} class="relative">
                    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-2xl shadow-black/20" aria-label="Ejemplo de envío de un informe" role="img">
                        <div class="flex items-center gap-1.5 border-b border-border px-4 py-3">
                            <span class="size-2.5 rounded-full bg-peligro/70"></span>
                            <span class="size-2.5 rounded-full bg-aviso/70"></span>
                            <span class="size-2.5 rounded-full bg-exito/70"></span>
                            <span class="ml-3 font-mono text-xs text-muted-foreground">~/informes</span>
                        </div>
                        <div class="min-h-[248px] space-y-2 p-5 font-mono text-[13px] leading-relaxed">
                            {#each lineas as linea, i (i)}
                                {#if i < escritas || (i === escritas && linea.tipo === 'cmd')}
                                    {#if linea.tipo === 'cmd'}
                                        <p><span class="text-primary">$</span> {i < escritas ? linea.texto : linea.texto.slice(0, caracteres)}{#if i === escritas}<span class="ml-0.5 inline-block h-4 w-2 translate-y-0.5 animate-pulse bg-primary"></span>{/if}</p>
                                    {:else if linea.tipo === 'ok'}
                                        <p class="text-muted-foreground"><span class="text-exito">✓</span> {linea.texto}</p>
                                    {:else}
                                        <p class="text-info">→ {linea.texto}</p>
                                    {/if}
                                {/if}
                            {/each}
                            {#if escritas >= lineas.length}
                                <p><span class="text-primary">$</span> <span class="inline-block h-4 w-2 translate-y-0.5 animate-pulse bg-primary"></span></p>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cifras -->
        <section class="border-y border-border bg-card/40" aria-label="Huella en cifras">
            <dl class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
                {#each [{ v: cifras.programas, t: 'programas públicos activos' }, { v: cifras.investigadores, t: 'investigadores registrados' }, { v: cifras.resueltas, t: 'vulnerabilidades validadas' }] as cifra, i (cifra.t)}
                    <div use:aparecer={i * 90} class="flex flex-col-reverse text-center sm:text-left">
                        <dt class="text-sm text-muted-foreground">{cifra.t}</dt>
                        <dd class="font-display text-4xl font-semibold text-primary" use:contador={cifra.v}>{cifra.v}</dd>
                    </div>
                {/each}
            </dl>
        </section>

        <!-- Cómo funciona -->
        <section id="como-funciona" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6">
            <div use:aparecer class="max-w-2xl">
                <p class="font-mono text-xs tracking-widest text-primary uppercase">Cómo funciona</p>
                <h2 class="mt-2 text-3xl font-semibold sm:text-4xl">Dos lados, un mismo objetivo: arreglar antes de que duela.</h2>
            </div>

            <div class="mt-8 inline-flex rounded-lg border border-border bg-card/60 p-1" role="tablist" aria-label="Para quién">
                {#each [{ v: 'investigadores', t: 'Para investigadores' }, { v: 'empresas', t: 'Para empresas' }] as op (op.v)}
                    <button
                        type="button"
                        role="tab"
                        aria-selected={publico === op.v}
                        class="rounded-md px-4 py-2 text-sm transition-colors {publico === op.v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}"
                        onclick={() => (publico = op.v as typeof publico)}
                    >
                        {op.t}
                    </button>
                {/each}
            </div>

            {#key publico}
                <ol class="mt-8 grid gap-4 md:grid-cols-3">
                    {#each pasos[publico] as paso, i (paso.titulo)}
                        {@const Icono = paso.icono}
                        <li use:aparecer={i * 90} class="relative rounded-xl border border-border bg-card p-6">
                            <span class="absolute top-5 right-5 font-mono text-xs text-muted-foreground">0{i + 1}</span>
                            <span class="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><Icono class="size-5" /></span>
                            <h3 class="font-sans text-base font-semibold">{paso.titulo}</h3>
                            <p class="mt-2 text-sm text-muted-foreground">{paso.texto}</p>
                        </li>
                    {/each}
                </ol>
            {/key}
        </section>

        <!-- Seguridad -->
        <section id="seguridad" class="scroll-mt-20 border-y border-border bg-card/40">
            <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <div use:aparecer class="max-w-2xl">
                    <p class="font-mono text-xs tracking-widest text-primary uppercase">Seguridad</p>
                    <h2 class="mt-2 text-3xl font-semibold sm:text-4xl">Una plataforma de seguridad tiene que predicar con el ejemplo.</h2>
                </div>
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    {#each pilares as pilar, i (pilar.titulo)}
                        {@const Icono = pilar.icono}
                        <div use:aparecer={(i % 2) * 90} class="flex gap-4 rounded-xl border border-border bg-background/60 p-6">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Icono class="size-5" /></span>
                            <div>
                                <h3 class="font-sans text-base font-semibold">{pilar.titulo}</h3>
                                <p class="mt-1.5 text-sm text-muted-foreground">{pilar.texto}</p>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        </section>

        <!-- Programas y líderes -->
        <section id="programas" class="mx-auto grid max-w-6xl scroll-mt-20 gap-10 px-4 py-20 sm:px-6 lg:grid-cols-[1.6fr_1fr]">
            <div>
                <div use:aparecer class="flex items-end justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-widest text-primary uppercase">Programas</p>
                        <h2 class="mt-2 text-3xl font-semibold">Programas abiertos ahora</h2>
                    </div>
                    <Link href="/programas" class="hidden shrink-0 items-center gap-1 text-sm text-primary hover:underline sm:inline-flex">Ver todos <ArrowRight class="size-4" /></Link>
                </div>
                {#if programas.length === 0}
                    <div class="mt-6 rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Pronto habrá programas públicos. Si tu empresa quiere ser la primera,
                        <Link href="/empresa/registro" class="text-primary underline underline-offset-4">regístrala aquí</Link>.
                    </div>
                {:else}
                    <ul class="mt-6 grid gap-3">
                        {#each programas as programa, i (programa.id)}
                            <li use:aparecer={i * 80}>
                                <Link href="/programas/{programa.id}" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-5 transition-colors hover:border-primary/50 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{programa.nombre}</p>
                                        <p class="text-sm text-muted-foreground">{programa.empresa ?? 'Empresa verificada'}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        {#each programa.tipos as tipo (tipo)}
                                            <span class="rounded-md bg-muted px-2 py-0.5 font-mono text-[11px] tracking-wide text-muted-foreground uppercase">{tipo}</span>
                                        {/each}
                                        {#if programa.nivel_acceso}<NivelAccesoBadge nivel={programa.nivel_acceso} />{/if}
                                    </div>
                                </Link>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </div>

            <aside use:aparecer={120} aria-labelledby="titulo-lideres">
                <p class="font-mono text-xs tracking-widest text-primary uppercase">Salón de la Fama</p>
                <h2 id="titulo-lideres" class="mt-2 text-3xl font-semibold">Quién deja más huella</h2>
                <ol class="mt-6 space-y-3">
                    {#each lideres as lider, i (lider.id)}
                        <li class="flex items-center gap-3 rounded-xl border border-border bg-card p-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full {i === 0 ? 'bg-aviso text-background' : 'bg-muted text-foreground'}">
                                {#if i === 0}<Trophy class="size-4" />{:else}<Medal class="size-4" />{/if}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{lider.name}</p>
                                <p class="font-mono text-xs text-muted-foreground">{lider.puntos} pts</p>
                            </div>
                            <RangoBadge puntos={lider.puntos} />
                        </li>
                    {:else}
                        <li class="rounded-xl border border-dashed border-border p-6 text-sm text-muted-foreground">El podio espera a su primer campeón.</li>
                    {/each}
                </ol>
                <Link href="/hall-of-fame" class="mt-4 inline-flex items-center gap-1 text-sm text-primary hover:underline">Ver el Salón de la Fama <ArrowRight class="size-4" /></Link>
            </aside>
        </section>

        <!-- Preguntas -->
        <section id="preguntas" class="scroll-mt-20 border-t border-border">
            <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
                <div use:aparecer class="text-center">
                    <p class="font-mono text-xs tracking-widest text-primary uppercase">Preguntas frecuentes</p>
                    <h2 class="mt-2 text-3xl font-semibold">Lo que suelen preguntarnos</h2>
                </div>
                <div class="mt-10 divide-y divide-border rounded-xl border border-border bg-card">
                    {#each preguntas as item (item.p)}
                        <details class="group p-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                                {item.p}
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 text-sm text-muted-foreground">{item.r}</p>
                        </details>
                    {/each}
                </div>
            </div>
        </section>

        <!-- Cierre -->
        <section class="px-4 pb-20 sm:px-6">
            <div use:aparecer class="relative mx-auto max-w-6xl overflow-hidden rounded-2xl border border-primary/30 bg-primary/10 px-6 py-14 text-center">
                <div class="pointer-events-none absolute -bottom-20 -left-10 size-64 text-primary/10" aria-hidden="true"><AppLogoIcon class="size-full" /></div>
                <h2 class="text-3xl font-semibold sm:text-4xl">¿Listo para dejar huella?</h2>
                <p class="mx-auto mt-3 max-w-xl text-muted-foreground">Crea tu cuenta en un minuto. Tus informes, cifrados desde el primer envío.</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <Button href="/register" size="lg"><UserPlus class="mr-1 size-4" /> Soy investigador</Button>
                    <Button href="/empresa/registro" size="lg" variant="outline"><Building2 class="mr-1 size-4" /> Soy empresa</Button>
                </div>
                <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                    <ScrollText class="size-3.5" /> Al registrarte aceptas los <Link href="/terminos" class="underline underline-offset-4">Términos</Link> y la
                    <Link href="/privacidad" class="underline underline-offset-4">Política de Privacidad</Link>.
                </p>
            </div>
        </section>
    </main>

    <SitioPie />
</div>
