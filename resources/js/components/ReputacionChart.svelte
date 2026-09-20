<script lang="ts">
    import { onMount } from 'svelte';
    import {
        Chart,
        LineController,
        LineElement,
        PointElement,
        LinearScale,
        CategoryScale,
        Tooltip,
        Filler,
    } from 'chart.js';
    import type { EntradaReputacion } from '@/types/domain';

    Chart.register(
        LineController,
        LineElement,
        PointElement,
        LinearScale,
        CategoryScale,
        Tooltip,
        Filler,
    );

    let {
        historial,
    }: {
        historial: EntradaReputacion[];
    } = $props();

    let canvas: HTMLCanvasElement | null = null;
    let chart: Chart | null = null;

    onMount(() => {
        const datos = [...historial].reverse();
        const labels = datos.map((e) =>
            new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short' }).format(
                new Date(e.created_at),
            ),
        );
        const saldos: number[] = [];
        let acumulado = 0;
        for (const e of datos) {
            acumulado += e.puntos;
            saldos.push(acumulado);
        }

        if (!canvas) return;

        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Saldo',
                        data: saldos,
                        borderColor: 'hsl(152, 68%, 47%)',
                        backgroundColor: 'hsla(152, 68%, 47%, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: 'hsl(152, 68%, 47%)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `Saldo: ${ctx.parsed.y} pts`,
                        },
                    },
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255,255,255,0.06)' },
                        ticks: { color: 'rgba(255,255,255,0.5)' },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: 'rgba(255,255,255,0.5)' },
                    },
                },
            },
        });

        return () => {
            chart?.destroy();
        };
    });
</script>

<div class="h-64 w-full">
    <canvas bind:this={canvas}></canvas>
</div>
