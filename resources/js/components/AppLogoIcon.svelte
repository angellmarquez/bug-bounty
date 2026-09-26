<script lang="ts">
    // Logo de Huella: una huella dactilar cuyas crestas son "líneas de código"
    // (trazos cortados como tokens). Usa currentColor para seguir al tema.
    let {
        class: className = '',
        ...rest
    }: {
        class?: string;
        [key: string]: unknown;
    } = $props();
</script>

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 32 32"
    fill="none"
    stroke="currentColor"
    stroke-width="2.2"
    stroke-linecap="round"
    class={className}
    aria-hidden="true"
    {...rest}
>
    <path d="M6.2 23.4A11 11 0 0 1 5 16 11 11 0 0 1 16 5c4.3 0 8 2.5 9.8 6.1" stroke-dasharray="6 2.6 11 2.6 4 2.6 30" />
    <path d="M27 16.4c0 3.2-.6 6.3-1.8 9.1" stroke-dasharray="3.5 2.6 20" />
    <path d="M9.6 26.2A21 21 0 0 1 8 16a8 8 0 0 1 16 0c0 2.8-.3 5.5-.9 7.9" stroke-dasharray="3 2.6 9 2.6 7 2.6 6 2.6 30" />
    <path d="M13 28.2A30 30 0 0 1 11 16a5 5 0 0 1 10 0c0 4.3-.6 8.1-1.8 11.4" stroke-dasharray="8 2.6 3 2.6 10 2.6 30" />
    <path d="M16 16c0 5-.8 9.4-2.3 12.9" stroke-dasharray="5 2.6 20" />
</svg>
