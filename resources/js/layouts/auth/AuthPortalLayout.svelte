<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import BadgeCheck from '@lucide/svelte/icons/badge-check';
    import Bug from '@lucide/svelte/icons/bug';
    import EyeOff from '@lucide/svelte/icons/eye-off';
    import FileLock2 from '@lucide/svelte/icons/file-lock-2';
    import ListChecks from '@lucide/svelte/icons/list-checks';
    import Trophy from '@lucide/svelte/icons/trophy';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { aparecer } from '@/lib/animaciones';

    // Acceso separado por público: cada uno ve su propio mensaje y su propio formulario.
    let {
        title = '',
        description = '',
        portal = 'investigador',
        modo = 'login',
        children,
    }: {
        title?: string;
        description?: string;
        portal?: 'investigador' | 'empresa';
        modo?: 'login' | 'registro' | 'otro';
        children?: Snippet;
    } = $props();

    const mensajes = {
        investigador: {
            etiqueta: 'Para investigadores',
            titular: 'Encuentra fallos.\nDeja huella.',
            puntos: [
                { icono: Bug, texto: 'Reporta con el formulario de cada programa, CVSS y fotos de evidencia.' },
                { icono: FileLock2, texto: 'Tus hallazgos se cifran con PGP desde el primer envío.' },
                { icono: Trophy, texto: 'Suma reputación, sube de rango y entra en programas de mayor nivel.' },
            ],
        },
        empresa: {
            etiqueta: 'Para empresas',
            titular: 'Tu programa de seguridad,\nsin ruido.',
            puntos: [
                { icono: ListChecks, texto: 'Define el alcance y la evidencia que necesitas; público o por invitación.' },
                { icono: EyeOff, texto: 'La moderación revisa a ciegas y descarta los informes sin valor.' },
                { icono: BadgeCheck, texto: 'Recibes solo informes validados, cifrados con la clave de tu empresa.' },
            ],
        },
    } as const;

    const actual = $derived(mensajes[portal]);

    // El selector lleva al mismo tipo de pantalla (entrar o registrarse) del otro público.
    const enlaces = $derived(
        modo === 'registro'
            ? { investigador: '/register', empresa: '/empresa/registro' }
            : { investigador: '/login', empresa: '/empresa/login' },
    );
</script>

<div class="grid min-h-svh bg-background lg:grid-cols-[1.05fr_1fr]" data-portal={portal}>
    <!-- Panel de marca -->
    <aside class="relative hidden overflow-hidden border-r border-border bg-card lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div class="pointer-events-none absolute -right-24 -bottom-24 size-[460px] text-primary/[0.07]" aria-hidden="true">
            <AppLogoIcon class="size-full" />
        </div>

        <Link href="/" class="relative flex items-center gap-2.5" aria-label="Huella, inicio">
            <span class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"><AppLogoIcon class="size-5" /></span>
            <span class="font-display text-xl font-semibold">huella</span>
        </Link>

        {#key portal}
            <div class="relative max-w-md" use:aparecer>
                <p class="font-mono text-xs tracking-widest text-primary uppercase">{actual.etiqueta}</p>
                <p class="mt-3 font-display text-4xl leading-tight font-semibold whitespace-pre-line">{actual.titular}</p>
                <ul class="mt-8 space-y-4">
                    {#each actual.puntos as punto (punto.texto)}
                        {@const Icono = punto.icono}
                        <li class="flex gap-3 text-sm text-muted-foreground">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Icono class="size-4" /></span>
                            <span class="pt-1.5">{punto.texto}</span>
                        </li>
                    {/each}
                </ul>
            </div>
        {/key}

        <p class="relative text-xs text-muted-foreground">
            <Link href="/terminos" class="hover:text-foreground">Términos</Link> ·
            <Link href="/privacidad" class="hover:text-foreground">Privacidad</Link> ·
            <Link href="/politica-de-divulgacion" class="hover:text-foreground">Divulgación</Link>
        </p>
    </aside>

    <!-- Formulario -->
    <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
        <div class="w-full max-w-sm">
            <Link href="/" class="mb-8 flex items-center justify-center gap-2.5 lg:hidden" aria-label="Huella, inicio">
                <span class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"><AppLogoIcon class="size-5" /></span>
                <span class="font-display text-xl font-semibold">huella</span>
            </Link>

            {#if modo !== 'otro'}
                <nav class="mb-8 grid grid-cols-2 rounded-lg border border-border bg-card p-1 text-sm" aria-label="Tipo de cuenta">
                    <Link
                        href={enlaces.investigador}
                        aria-current={portal === 'investigador' ? 'page' : undefined}
                        class="rounded-md px-3 py-2 text-center transition-colors {portal === 'investigador' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}"
                        data-test="portal-investigador"
                    >
                        Investigadores
                    </Link>
                    <Link
                        href={enlaces.empresa}
                        aria-current={portal === 'empresa' ? 'page' : undefined}
                        class="rounded-md px-3 py-2 text-center transition-colors {portal === 'empresa' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}"
                        data-test="portal-empresa"
                    >
                        Empresas
                    </Link>
                </nav>
            {/if}

            <div class="mb-6 space-y-1.5">
                <h1 class="text-2xl font-semibold">{title}</h1>
                {#if description}<p class="text-sm text-muted-foreground">{description}</p>{/if}
            </div>

            {@render children?.()}
        </div>
    </main>
</div>
