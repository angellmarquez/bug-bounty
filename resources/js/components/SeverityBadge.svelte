<script lang="ts">
    import type { Severidad } from '@/types/enums';
    import { severidadColor, severidadLabel } from '@/lib/status-colors';

    let { severidad }: { severidad: Severidad } = $props();
</script>

<span class={severidadColor(severidad)}>
    {severidadLabel(severidad)}
</span>
