<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const anio = new Date().getFullYear();

    const columnas = [
        {
            titulo: 'Investigadores',
            enlaces: [
                { href: '/register', texto: 'Crear cuenta' },
                { href: '/login', texto: 'Iniciar sesión' },
                { href: '/hall-of-fame', texto: 'Salón de la Fama' },
            ],
        },
        {
            titulo: 'Empresas',
            enlaces: [
                { href: '/empresa/registro', texto: 'Registrar empresa' },
                { href: '/empresa/login', texto: 'Acceso empresas' },
                { href: '/politica-de-divulgacion', texto: 'Divulgación coordinada' },
            ],
        },
        {
            titulo: 'Legal',
            enlaces: [
                { href: '/terminos', texto: 'Términos de Servicio' },
                { href: '/privacidad', texto: 'Política de Privacidad' },
                { href: '/politica-de-divulgacion', texto: 'Política de Divulgación' },
            ],
        },
    ];
</script>

<footer class="border-t border-border bg-card/40">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_repeat(3,1fr)]">
        <div class="space-y-3">
            <Link href="/" class="flex items-center gap-2.5" aria-label="Huella, inicio">
                <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-5" />
                </span>
                <span class="font-display text-lg font-semibold">huella</span>
            </Link>
            <p class="max-w-xs text-sm text-muted-foreground">
                Divulgación coordinada de vulnerabilidades: cada hallazgo deja huella, cifrada y verificable.
            </p>
        </div>

        {#each columnas as columna (columna.titulo)}
            <div>
                <h2 class="mb-3 font-sans text-sm font-semibold">{columna.titulo}</h2>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    {#each columna.enlaces as enlace (enlace.href + enlace.texto)}
                        <li><Link href={enlace.href} class="transition-colors hover:text-foreground">{enlace.texto}</Link></li>
                    {/each}
                </ul>
            </div>
        {/each}
    </div>
    <div class="border-t border-border/60">
        <p class="mx-auto max-w-6xl px-4 py-5 text-xs text-muted-foreground sm:px-6">
            © {anio} Huella. Hecho para que investigar sea seguro y reportar, fácil.
        </p>
    </div>
</footer>
