<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import { cn } from '@/lib/utils';

    let {
        descripciones = false,
        class: className = '',
    }: {
        /** Muestra debajo de cada rol qué puede hacer. */
        descripciones?: boolean;
        class?: string;
    } = $props();

    const ROLES: Record<string, { etiqueta: string; descripcion: string; clase: string }> = {
        administrador: {
            etiqueta: 'Administrador',
            descripcion: 'Gestionas la plataforma: empresas, moderadores, usuarios y configuración.',
            clase: 'border-chart-3/40 bg-chart-3/10 text-chart-3',
        },
        moderador: {
            etiqueta: 'Moderador',
            descripcion:
                'Revisas los informes enviados a los programas: validas, rechazas (y penalizas reportes falsos) o marcas duplicados desde Moderación.',
            clase: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
        },
        empresa: {
            etiqueta: 'Empresa',
            descripcion: 'Publicas y gestionas los programas de tu empresa y lees los informes que recibe.',
            clase: 'border-chart-5/40 bg-chart-5/10 text-chart-5',
        },
        gestion: {
            etiqueta: 'Gestión',
            descripcion: 'Gestionas programas heredados de la plataforma.',
            clase: 'border-chart-4/40 bg-chart-4/10 text-chart-4',
        },
        investigador: {
            etiqueta: 'Investigador',
            descripcion: 'Buscas vulnerabilidades en los programas publicados y envías informes.',
            clase: 'border-chart-1/40 bg-chart-1/10 text-chart-1',
        },
    };

    const ORDEN = ['administrador', 'moderador', 'empresa', 'gestion', 'investigador'];

    const roles = $derived(
        ORDEN.filter((rol) => ((page.props.userRoles as string[] | undefined) ?? []).includes(rol)),
    );
</script>

{#if roles.length > 0}
    {#if descripciones}
        <ul class={cn('space-y-3', className)} data-test="roles-usuario">
            {#each roles as rol (rol)}
                <li class="space-y-1">
                    <span
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {ROLES[rol].clase}"
                        data-rol={rol}
                    >
                        {#if rol === 'moderador'}<ShieldCheck class="h-3 w-3" />{/if}
                        {ROLES[rol].etiqueta}
                    </span>
                    <p class="text-sm text-muted-foreground">{ROLES[rol].descripcion}</p>
                </li>
            {/each}
        </ul>
    {:else}
        <div class={cn('flex flex-wrap items-center gap-2', className)} data-test="roles-usuario">
            {#each roles as rol (rol)}
                <span
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-semibold {ROLES[rol].clase}"
                    data-rol={rol}
                >
                    {#if rol === 'moderador'}<ShieldCheck class="h-3 w-3" />{/if}
                    {ROLES[rol].etiqueta}
                </span>
            {/each}
        </div>
    {/if}
{/if}
