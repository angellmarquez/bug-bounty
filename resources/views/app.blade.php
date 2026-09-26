<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark']) data-tema-elegido="{{ $tema ?? 'terminal' }}" @if(($tema ?? 'terminal') !== 'auto') data-tema="{{ $tema ?? 'terminal' }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">

        {{-- Tema "Automático": se resuelve antes de pintar para que no parpadee. --}}
        <script>
            (function () {
                var raiz = document.documentElement;
                if (raiz.dataset.tema) return;
                var oscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
                raiz.dataset.tema = oscuro ? 'terminal' : 'corporativo';
                raiz.classList.toggle('dark', oscuro);
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Huella') }}</title>
        </x-inertia::head>
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
