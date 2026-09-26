<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Menu from '@lucide/svelte/icons/menu';
    import X from '@lucide/svelte/icons/x';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { Button } from '@/components/ui/button';

    // En la landing los enlaces van a sus secciones; desde otras páginas públicas, a la landing.
    let { enInicio = false }: { enInicio?: boolean } = $props();

    const usuario = $derived(page.props.auth?.user);
    let abierto = $state(false);

    const secciones = [
        { href: '#como-funciona', texto: 'Cómo funciona' },
        { href: '#seguridad', texto: 'Seguridad' },
        { href: '#programas', texto: 'Programas' },
        { href: '#preguntas', texto: 'Preguntas' },
    ];
</script>

<header class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Link href="/" class="flex items-center gap-2.5" aria-label="Huella, inicio">
            <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <AppLogoIcon class="size-5" />
            </span>
            <span class="font-display text-lg font-semibold tracking-tight">huella</span>
        </Link>

        <nav class="hidden items-center gap-6 text-sm text-muted-foreground md:flex" aria-label="Secciones">
            {#each secciones as s (s.href)}
                <a href={enInicio ? s.href : `/${s.href}`} class="transition-colors hover:text-foreground">{s.texto}</a>
            {/each}
            <Link href="/hall-of-fame" class="transition-colors hover:text-foreground">Salón de la Fama</Link>
        </nav>

        <div class="hidden items-center gap-2 md:flex">
            {#if usuario}
                <Button href="/dashboard" size="sm">Ir a mi panel</Button>
            {:else}
                <Button href="/empresa/login" variant="ghost" size="sm">Acceso empresas</Button>
                <Button href="/login" variant="outline" size="sm">Acceso investigadores</Button>
                <Button href="/register" size="sm">Crear cuenta</Button>
            {/if}
        </div>

        <button
            type="button"
            class="rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground md:hidden"
            aria-label={abierto ? 'Cerrar menú' : 'Abrir menú'}
            aria-expanded={abierto}
            onclick={() => (abierto = !abierto)}
        >
            {#if abierto}<X class="size-5" />{:else}<Menu class="size-5" />{/if}
        </button>
    </div>

    {#if abierto}
        <div class="border-t border-border bg-background px-4 py-4 md:hidden">
            <nav class="flex flex-col gap-3 text-sm" aria-label="Secciones">
                {#each secciones as s (s.href)}
                    <a href={enInicio ? s.href : `/${s.href}`} class="text-muted-foreground hover:text-foreground" onclick={() => (abierto = false)}>{s.texto}</a>
                {/each}
                <Link href="/hall-of-fame" class="text-muted-foreground hover:text-foreground">Salón de la Fama</Link>
            </nav>
            <div class="mt-4 grid gap-2">
                {#if usuario}
                    <Button href="/dashboard">Ir a mi panel</Button>
                {:else}
                    <Button href="/register">Crear cuenta de investigador</Button>
                    <Button href="/login" variant="outline">Acceso investigadores</Button>
                    <Button href="/empresa/login" variant="ghost">Acceso empresas</Button>
                {/if}
            </div>
        </div>
    {/if}
</header>
