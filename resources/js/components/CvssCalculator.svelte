<script lang="ts">
    import {
        CVSS_METRICS,
        parseCvssVector,
        buildCvssVector,
        calcularPuntuacionBase,
        severidadDePuntuacion,
    } from '@/lib/cvss';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import SeverityBadge from '@/components/SeverityBadge.svelte';
    import type { Severidad } from '@/types/enums';

    let {
        vector = '',
        onChange,
    }: {
        vector?: string;
        onChange: (vector: string, score: number, severidad: Severidad) => void;
    } = $props();

    let metrics = $state<Record<string, string>>(parseCvssVector(vector));

    let score = $derived(calcularPuntuacionBase(metrics));
    let severidad = $derived(severidadDePuntuacion(score));
    let vectorCompleto = $derived(buildCvssVector(metrics));

    function cambiarMetrica(id: string, valor: string) {
        metrics = { ...metrics, [id]: valor };
        onChange(vectorCompleto, score, severidad);
    }
</script>

<Card>
    <CardHeader>
        <CardTitle>Calculadora CVSS 3.1</CardTitle>
    </CardHeader>
    <CardContent class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            {#each CVSS_METRICS as metric (metric.id)}
                <div class="space-y-2">
                    <label class="text-sm font-medium text-foreground">
                        {metric.label}
                    </label>
                    <Select
                        value={metrics[metric.id] ?? metric.values[0].id}
                        onValueChange={(v) => cambiarMetrica(metric.id, v)}
                    >
                        <SelectTrigger class="w-full">
                            <span>
                                {metric.values.find((v) => v.id === (metrics[metric.id] ?? metric.values[0].id))?.label ?? 'Select'}
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            {#each metric.values as value (value.id)}
                                <SelectItem value={value.id}>
                                    {value.label}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                </div>
            {/each}
        </div>

        <div class="flex items-center gap-4 rounded-lg border border-border bg-card p-4">
            <div class="text-center">
                <p class="text-3xl font-bold text-primary">{score.toFixed(1)}</p>
                <p class="text-xs text-muted-foreground">Score Base</p>
            </div>
            <div class="h-10 w-px bg-border"></div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">Severidad:</span>
                <SeverityBadge {severidad} />
            </div>
            <div class="h-10 w-px bg-border"></div>
            <div class="flex-1">
                <p class="text-xs text-muted-foreground">Vector</p>
                <code class="block truncate text-xs">{vectorCompleto}</code>
            </div>
        </div>
    </CardContent>
</Card>
