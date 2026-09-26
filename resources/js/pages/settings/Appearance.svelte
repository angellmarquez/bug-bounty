<script module lang="ts">
    import { edit as editAppearance } from '@/routes/appearance';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: editAppearance(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import TemaSelector from '@/components/TemaSelector.svelte';
</script>

<AppHead title="Apariencia" />

<h1 class="sr-only">Apariencia</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Tema visual"
        description="Elige cómo se ve Huella. Se guarda en tu cuenta y se aplica en todos tus dispositivos."
    />
    <TemaSelector />
</div>
