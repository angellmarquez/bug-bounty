<script lang="ts">
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import { Button } from '@/components/ui/button';
    import type { NavItem } from '@/types';

    let {
        href,
        etiqueta = 'Volver',
    }: {
        href: NavItem['href'];
        etiqueta?: string;
    } = $props();
</script>

<!-- El destino es contextual (según la página y el rol), no el historial: así "volver" tras
     guardar un formulario no regresa al formulario. -->
<Button variant="ghost" size="sm" {href} data-test="boton-volver" class="shrink-0">
    <ArrowLeft class="h-4 w-4" />
    {etiqueta}
</Button>
