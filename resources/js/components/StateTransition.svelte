<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import Input from '@/components/ui/input/Input.svelte';
    import type { EstadoReporte } from '@/types/enums';

    type TransicionAccion =
        | 'asignar'
        | 'validar'
        | 'rechazar'
        | 'marcar_duplicado'
        | 'pagar'
        | 'cerrar';

    let {
        open = $bindable(false),
        accion,
        reporteId,
        estadoActual,
        usuariosGestion = [],
        onsuccess,
    }: {
        open: boolean;
        accion: TransicionAccion;
        reporteId: number;
        estadoActual: EstadoReporte;
        usuariosGestion?: { id: number; name: string }[];
        onsuccess?: () => void;
    } = $props();

    let nota = $state('');
    let asignadoA = $state<string>('');
    let reporteDuplicadoId = $state('');
    let recompensa = $state('');
    let sancionar = $state(false);
    let gravedadSancion = $state('leve');
    let processing = $state(false);

    const rutaMap: Record<TransicionAccion, string> = {
        asignar: 'asignar',
        validar: 'validar',
        rechazar: 'rechazar',
        marcar_duplicado: 'marcar-duplicado',
        pagar: 'pagar',
        cerrar: 'cerrar',
    };

    const accionConfig = $derived.by(() => {
        const configs: Record<TransicionAccion, { titulo: string; descripcion: string; variante: 'default' | 'destructive' | 'outline' }> = {
            asignar: {
                titulo: 'Asignar reporte',
                descripcion: 'Selecciona el analista que se encargara del triaje de este reporte.',
                variante: 'default',
            },
            validar: {
                titulo: 'Validar reporte',
                descripcion: 'El reporte sera marcado como validado. Esta accion no se puede deshacer.',
                variante: 'default',
            },
            rechazar: {
                titulo: 'Rechazar reporte',
                descripcion: 'El reporte sera rechazado. Considera agregar una nota explicativa.',
                variante: 'destructive',
            },
            marcar_duplicado: {
                titulo: 'Marcar como duplicado',
                descripcion: 'Vincula este reporte con el reporte original del cual es duplicado.',
                variante: 'outline',
            },
            pagar: {
                titulo: 'Registrar pago',
                descripcion: 'Registra la recompensa pagada al investigador.',
                variante: 'default',
            },
            cerrar: {
                titulo: 'Cerrar reporte',
                descripcion: 'El reporte sera cerrado definitivamente.',
                variante: 'destructive',
            },
        };
        return configs[accion];
    });

    function submit() {
        processing = true;

        const data: Record<string, string> = {};
        if (nota) data.nota = nota;
        if (accion === 'asignar' && asignadoA) data.asignado_a = asignadoA;
        if (accion === 'marcar_duplicado' && reporteDuplicadoId) data.reporte_duplicado_id = reporteDuplicadoId;
        if (accion === 'pagar' && recompensa) data.recompensa = recompensa;
        if (accion === 'rechazar' && sancionar) {
            data.sancionar = '1';
            data.gravedad_sancion = gravedadSancion;
        }

        router.post(`/reportes/${reporteId}/${rutaMap[accion]}`, data, {
            preserveScroll: true,
            onSuccess: () => {
                open = false;
                resetForm();
                onsuccess?.();
            },
            onFinish: () => {
                processing = false;
            },
        });
    }

    function resetForm() {
        nota = '';
        asignadoA = '';
        reporteDuplicadoId = '';
        recompensa = '';
        sancionar = false;
        gravedadSancion = 'leve';
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-md">
        <div class="space-y-2">
            <DialogTitle>{accionConfig.titulo}</DialogTitle>
            <DialogDescription>{accionConfig.descripcion}</DialogDescription>
        </div>

        <div class="space-y-4 py-2">
            {#if accion === 'asignar'}
                <div class="space-y-2">
                    <Label for="asignado_a">Analista</Label>
                    <Select type="single" bind:value={asignadoA}>
                        <SelectTrigger class="w-full">
                            {#snippet children()}
                                {usuariosGestion.find((u) => String(u.id) === asignadoA)?.name ?? 'Seleccionar analista...'}
                            {/snippet}
                        </SelectTrigger>
                        <SelectContent>
                            {#each usuariosGestion as usuario (usuario.id)}
                                <SelectItem value={String(usuario.id)}>
                                    {usuario.name}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </Select>
                </div>
            {/if}

            {#if accion === 'marcar_duplicado'}
                <div class="space-y-2">
                    <Label for="reporte_duplicado_id">ID del reporte original</Label>
                    <Input
                        id="reporte_duplicado_id"
                        type="number"
                        bind:value={reporteDuplicadoId}
                        placeholder="Ej: 42"
                    />
                </div>
            {/if}

            {#if accion === 'pagar'}
                <div class="space-y-2">
                    <Label for="recompensa">Monto de recompensa</Label>
                    <Input
                        id="recompensa"
                        type="number"
                        step="0.01"
                        min="0"
                        bind:value={recompensa}
                        placeholder="Ej: 500.00"
                    />
                </div>
            {/if}

            {#if accion === 'rechazar'}
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={sancionar} />
                    Marcar como reporte falso y aplicar sanción
                </label>
                {#if sancionar}
                    <div class="space-y-2">
                        <Label for="gravedad_sancion">Gravedad de la sanción</Label>
                        <select id="gravedad_sancion" bind:value={gravedadSancion} class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="leve">Leve</option>
                            <option value="media">Media</option>
                            <option value="grave">Grave</option>
                        </select>
                    </div>
                {/if}
            {/if}

            {#if accion !== 'validar' && accion !== 'cerrar'}
                <div class="space-y-2">
                    <Label for="nota">Nota {accion === 'rechazar' ? '(recomendada)' : '(opcional)'}</Label>
                    <textarea
                        id="nota"
                        bind:value={nota}
                        placeholder="Agrega una nota o justificacion..."
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    ></textarea>
                </div>
            {/if}
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => { open = false; resetForm(); }}>
                Cancelar
            </Button>
            <Button
                variant={accionConfig.variante}
                onclick={submit}
                disabled={processing || (accion === 'asignar' && !asignadoA) || (accion === 'marcar_duplicado' && !reporteDuplicadoId) || (accion === 'pagar' && !recompensa)}
            >
                {#if processing}
                    Procesando...
                {:else}
                    Confirmar
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
