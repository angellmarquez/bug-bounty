<script lang="ts">
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import type { PasoApelacion } from '@/types/domain';

    let {
        eventos,
        cadenaValida = true,
        completa = false,
    }: {
        eventos: PasoApelacion[];
        cadenaValida?: boolean;
        // Con `completa` se ven nombres, IP y navegador (moderadores y administradores);
        // sin ella solo el rol de quien decidió (el investigador sancionado).
        completa?: boolean;
    } = $props();

    const TIPOS: Record<string, { etiqueta: string; clase: string }> = {
        presentada: { etiqueta: 'Apelación presentada', clase: 'bg-amber-500' },
        aprobada: { etiqueta: 'Apelación aprobada', clase: 'bg-emerald-500' },
        rechazada: { etiqueta: 'Apelación rechazada', clase: 'bg-red-500' },
    };

    const ROLES: Record<string, string> = {
        administrador: 'Administrador',
        moderador: 'Moderador',
        investigador: 'Investigador',
        empresa: 'Empresa',
        sistema: 'Sistema (auditoría automática)',
    };

    function fecha(iso: string | null): string {
        if (!iso) return '';
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    }

    function quien(paso: PasoApelacion): string {
        const rol = ROLES[paso.actor_rol ?? 'sistema'] ?? paso.actor_rol ?? 'Desconocido';
        return completa && paso.actor?.name ? `${paso.actor.name} · ${rol}` : rol;
    }
</script>

<div class="space-y-4" data-test="traza">
    <div
        class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm {cadenaValida
            ? 'border-emerald-500/40 bg-emerald-500/10'
            : 'border-red-500/40 bg-red-500/10'}"
        data-test={cadenaValida ? 'cadena-valida' : 'cadena-alterada'}
        role={cadenaValida ? undefined : 'alert'}
    >
        {#if cadenaValida}
            <ShieldCheck class="h-4 w-4 text-emerald-600" />
            <span>Registro íntegro: las huellas de cada paso coinciden, nada fue alterado.</span>
        {:else}
            <ShieldAlert class="h-4 w-4 text-red-600" />
            <span>Atención: las huellas no coinciden, este registro fue modificado.</span>
        {/if}
    </div>

    <ol class="relative space-y-5 border-l border-border pl-6">
        {#each eventos as paso (paso.id)}
            <li class="relative" data-test="paso-traza">
                <span class="absolute -left-[31px] top-1 h-3 w-3 rounded-full {TIPOS[paso.tipo]?.clase ?? 'bg-slate-400'}"></span>
                <p class="text-sm font-medium">{TIPOS[paso.tipo]?.etiqueta ?? paso.tipo}</p>
                <p class="text-xs text-muted-foreground">
                    <span data-test="paso-actor">{quien(paso)}</span> · {fecha(paso.created_at)}
                </p>
                {#if paso.nota}
                    <p class="mt-1 whitespace-pre-wrap rounded-md bg-muted/50 px-3 py-2 text-sm">{paso.nota}</p>
                {/if}
                {#if completa && paso.datos?.mismo_que_sanciono}
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                        Quien resolvió es quien aplicó la sanción (administrador).
                    </p>
                {/if}
                <p class="mt-1 font-mono text-[11px] text-muted-foreground" title={paso.huella}>
                    Huella {paso.huella.slice(0, 16)}…
                </p>
                {#if completa}
                    <p class="text-[11px] text-muted-foreground">
                        IP {paso.ip ?? 'desconocida'}{paso.user_agent ? ` · ${paso.user_agent}` : ''}
                    </p>
                {/if}
            </li>
        {/each}
    </ol>
</div>
