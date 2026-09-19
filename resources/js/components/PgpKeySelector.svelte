<script lang="ts">
    import Key from '@lucide/svelte/icons/key';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import EmptyState from '@/components/EmptyState.svelte';
    import {
        Card,
        CardContent,
    } from '@/components/ui/card';
    import { Label } from '@/components/ui/label';
    import type { ClavePgpResumen } from '@/types/domain';

    let {
        claves = [],
        value = $bindable(null),
    }: {
        claves: ClavePgpResumen[];
        value: string | null;
    } = $props();

    function seleccionar(id: string | null) {
        value = id;
    }
</script>

{#if claves.length === 0}
    <EmptyState
        icon={Key}
        title="Sin claves PGP"
        description="No tienes claves PGP registradas. Puedes omitir el cifrado o registrar una clave primero."
    />
{:else}
    <div class="space-y-4">
        <Label>Clave PGP para cifrar la descripcion (opcional)</Label>

        <div
            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors {value === null ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted'}"
            onclick={() => seleccionar(null)}
            role="button"
            tabindex="0"
            onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') seleccionar(null); }}
        >
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                <ShieldCheck class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium">Sin cifrado</p>
                <p class="text-xs text-muted-foreground">Enviar en texto plano</p>
            </div>
            {#if value === null}
                <div class="h-3 w-3 rounded-full border-2 border-primary"></div>
            {/if}
        </div>

        {#each claves as clave (clave.id)}
            <div
                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors {value === String(clave.id) ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted'}"
                onclick={() => seleccionar(String(clave.id))}
                role="button"
                tabindex="0"
                onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') seleccionar(String(clave.id)); }}
            >
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                    <Key class="h-4 w-4 text-primary" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium">
                        {clave.huella?.substring(0, 16) ?? 'Clave'}...
                        {#if clave.es_principal}
                            <span class="ml-1 text-xs text-primary">(principal)</span>
                        {/if}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {clave.algoritmo ?? 'RSA'} · {clave.bits ?? 4096} bits
                    </p>
                </div>
                {#if value === String(clave.id)}
                    <div class="h-3 w-3 rounded-full border-2 border-primary"></div>
                {/if}
            </div>
        {/each}
    </div>
{/if}
