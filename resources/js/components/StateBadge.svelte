<script lang="ts">
    import type { EstadoReporte } from '@/types/enums';
    import {
        estadoReporteColor,
        estadoReporteLabel,
    } from '@/lib/status-colors';

    let { estado }: { estado: EstadoReporte } = $props();
</script>

<span class={estadoReporteColor(estado)}>
    {estadoReporteLabel(estado)}
</span>
