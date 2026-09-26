<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import SitioCabecera from '@/components/publico/SitioCabecera.svelte';
    import SitioPie from '@/components/publico/SitioPie.svelte';

    import type { Seccion } from '@/lib/legal';

    let {
        titulo,
        resumen,
        version,
        secciones,
    }: {
        titulo: string;
        resumen: string;
        version: string;
        secciones: Seccion[];
    } = $props();

    const documentos = [
        { href: '/terminos', texto: 'Términos de Servicio' },
        { href: '/privacidad', texto: 'Política de Privacidad' },
        { href: '/politica-de-divulgacion', texto: 'Política de Divulgación' },
    ];

    const fecha = $derived(new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${version}T00:00:00`)));
</script>

<AppHead title={titulo} />

<div class="min-h-screen bg-background text-foreground">
    <SitioCabecera />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[220px_1fr]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <nav aria-label="Documentos legales" class="space-y-1 text-sm">
                {#each documentos as doc (doc.href)}
                    <Link
                        href={doc.href}
                        class="block rounded-md px-3 py-2 transition-colors {doc.texto === titulo ? 'bg-primary/10 font-medium text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
                    >
                        {doc.texto}
                    </Link>
                {/each}
            </nav>
            <nav aria-label="En esta página" class="mt-6 hidden border-t border-border pt-4 text-xs lg:block">
                <p class="mb-2 font-medium text-muted-foreground">En esta página</p>
                <ol class="space-y-1.5">
                    {#each secciones as s, i (s.id)}
                        <li><a href="#{s.id}" class="text-muted-foreground hover:text-foreground">{i + 1}. {s.titulo}</a></li>
                    {/each}
                </ol>
            </nav>
        </aside>

        <article class="max-w-3xl" data-test="documento-legal">
            <div class="mb-8 flex gap-3 rounded-lg border border-aviso/40 bg-aviso/10 p-4 text-sm" role="note">
                <TriangleAlert class="mt-0.5 size-4 shrink-0 text-aviso" aria-hidden="true" />
                <p>
                    <strong>Borrador pendiente de revisión legal.</strong>
                    Este texto es una propuesta y debe revisarlo un profesional antes de publicarse.
                    Los datos entre corchetes se completan con los del operador.
                </p>
            </div>

            <h1 class="text-3xl font-semibold sm:text-4xl">{titulo}</h1>
            <p class="mt-2 text-sm text-muted-foreground">Versión {version} · vigente desde el {fecha}</p>
            <p class="mt-6 text-base text-muted-foreground">{resumen}</p>

            <div class="mt-10 space-y-10">
                {#each secciones as s, i (s.id)}
                    <section id={s.id} class="scroll-mt-24">
                        <h2 class="font-sans text-xl font-semibold">{i + 1}. {s.titulo}</h2>
                        {#each s.parrafos ?? [] as parrafo, j (j)}
                            <p class="mt-3 leading-relaxed text-foreground/90">{parrafo}</p>
                        {/each}
                        {#if s.lista}
                            <ul class="mt-3 list-disc space-y-1.5 pl-5 leading-relaxed text-foreground/90 marker:text-primary">
                                {#each s.lista as item, j (j)}
                                    <li>{item}</li>
                                {/each}
                            </ul>
                        {/if}
                    </section>
                {/each}
            </div>
        </article>
    </div>

    <SitioPie />
</div>
