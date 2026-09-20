<script lang="ts">
    import Key from '@lucide/svelte/icons/key';
    import Button from '@/components/ui/button/Button.svelte';
    import Card from '@/components/ui/card/Card.svelte';
    import CardContent from '@/components/ui/card/CardContent.svelte';
    import CardHeader from '@/components/ui/card/CardHeader.svelte';
    import CardTitle from '@/components/ui/card/CardTitle.svelte';
    import type { ClavePgp } from '@/types/domain';
    import {
        estadoClavePgpColor,
        estadoClavePgpLabel,
    } from '@/lib/status-colors';

    let {
        clave,
        onVerificar,
        onRevocar,
    }: {
        clave: ClavePgp;
        onVerificar?: (id: number) => void;
        onRevocar?: (id: number) => void;
    } = $props();

    function formatDate(dateStr: string | null): string | null {
        if (!dateStr) return null;
        return new Intl.DateTimeFormat('es-ES', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(dateStr));
    }
</script>

<Card>
    <CardHeader class="pb-2">
        <div class="flex items-start justify-between gap-4">
            <CardTitle class="flex items-center gap-2 text-sm font-medium">
                <Key class="h-4 w-4 text-muted-foreground" />
                {clave.huella}
            </CardTitle>
            <div class="flex shrink-0 items-center gap-2">
                <span class={estadoClavePgpColor(clave.estado)}>
                    {estadoClavePgpLabel(clave.estado)}
                </span>
                {#if clave.es_principal}
                    <span class="inline-flex items-center rounded-md border border-transparent bg-chart-1 px-2 py-0.5 text-xs font-semibold text-white">
                        Principal
                    </span>
                {/if}
            </div>
        </div>
    </CardHeader>
    <CardContent>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
            {#if clave.algoritmo}
                <span>
                    Algoritmo: <span class="font-medium text-foreground">{clave.algoritmo}</span>
                </span>
            {/if}
            {#if clave.bits}
                <span>
                    Bits: <span class="font-medium text-foreground">{clave.bits}</span>
                </span>
            {/if}
            {#if formatDate(clave.creada_en)}
                <span>Creada: {formatDate(clave.creada_en)}</span>
            {/if}
            {#if formatDate(clave.expira_en)}
                <span>Expira: {formatDate(clave.expira_en)}</span>
            {/if}
            {#if formatDate(clave.verificada_en)}
                <span>Verificada: {formatDate(clave.verificada_en)}</span>
            {/if}
            {#if formatDate(clave.ultimo_uso_en)}
                <span>Último uso: {formatDate(clave.ultimo_uso_en)}</span>
            {/if}
        </div>
        {#if (onVerificar && clave.estado === 'pendiente_verificacion') || (onRevocar && (clave.estado === 'activa' || clave.estado === 'pendiente_verificacion'))}
            <div class="mt-4 flex gap-2">
                {#if onVerificar && clave.estado === 'pendiente_verificacion'}
                    <Button
                        variant="outline"
                        size="sm"
                        onclick={() => onVerificar(clave.id)}
                    >
                        Verificar
                    </Button>
                {/if}
                {#if onRevocar && (clave.estado === 'activa' || clave.estado === 'pendiente_verificacion')}
                    <Button
                        variant="destructive"
                        size="sm"
                        onclick={() => onRevocar(clave.id)}
                    >
                        Revocar
                    </Button>
                {/if}
            </div>
        {/if}
    </CardContent>
</Card>
