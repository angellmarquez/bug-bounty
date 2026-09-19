<script lang="ts">
    import type { EstadoPrograma } from '@/types/enums';
    import {
        estadoProgramaColor,
        estadoProgramaLabel,
    } from '@/lib/status-colors';

    let { estado }: { estado: EstadoPrograma } = $props();
</script>

<span class={estadoProgramaColor(estado)}>
    {estadoProgramaLabel(estado)}
</span>
