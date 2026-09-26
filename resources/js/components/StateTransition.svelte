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
        | 'pedir_info'
        | 'validar'
        | 'rechazar'
        | 'marcar_duplicado'
        | 'reparacion'
        | 'cerrar';

    let {
        open = $bindable(false),
        accion,
        reporteId,
        estadoActual,
        moderadoresAsignables = [],
        candidatosDuplicado = [],
        onsuccess,
    }: {
        open: boolean;
        accion: TransicionAccion;
        reporteId: number;
        estadoActual: EstadoReporte;
        moderadoresAsignables?: { id: number; name: string }[];
        candidatosDuplicado?: { id: number; numero_reporte: string; titulo: string; estado: string }[];
        onsuccess?: () => void;
    } = $props();

    let nota = $state('');
    let asignadoA = $state<string>('');
    let reporteDuplicadoId = $state('');
    let sancionar = $state(false);
    let gravedadSancion = $state('leve');
    let motivoRechazo = $state('');
    let processing = $state(false);

    const MOTIVOS_RECHAZO_TEXTO: Record<string, string> = {
        falso_positivo: 'El reporte corresponde a un falso positivo o salida de escáner automatizado sin explotación real.',
        fuera_de_alcance: 'El activo o tipo de vulnerabilidad reportada está fuera del alcance de este programa.',
        sin_impacto: 'La observación representa una práctica informativa o sin impacto demostrable de seguridad.',
        falta_evidencia: 'Los pasos de reproducción no permitieron replicar el fallo en el entorno de pruebas.',
    };

    function alCambiarMotivoRechazo() {
        if (motivoRechazo && MOTIVOS_RECHAZO_TEXTO[motivoRechazo] && !nota) {
            nota = MOTIVOS_RECHAZO_TEXTO[motivoRechazo];
        }
        if (motivoRechazo === 'falso_positivo') {
            sancionar = true;
        }
    }

    const rutaMap: Record<TransicionAccion, string> = {
        asignar: 'asignar',
        pedir_info: 'pedir-info',
        validar: 'validar',
        rechazar: 'rechazar',
        marcar_duplicado: 'marcar-duplicado',
        reparacion: 'reparacion',
        cerrar: 'cerrar',
    };

    const accionConfig = $derived.by(() => {
        const configs: Record<TransicionAccion, { titulo: string; descripcion: string; variante: 'default' | 'destructive' | 'outline' }> = {
            asignar: {
                titulo: 'Asignar reporte',
                descripcion: 'Selecciona el analista que se encargara del triaje de este reporte.',
                variante: 'default',
            },
            pedir_info: {
                titulo: 'Solicitar más información',
                descripcion: 'Pide al investigador que aclare o aporte detalles adicionales para completar el triaje.',
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
            reparacion: {
                titulo: 'Marcar en reparación',
                descripcion: 'Indica que tu equipo ya está corrigiendo la vulnerabilidad. El investigador lo verá en su línea de tiempo.',
                variante: 'default',
            },
            cerrar: {
                titulo: 'Cerrar como resuelto',
                descripcion: 'La vulnerabilidad quedó corregida. El informe se cierra y el investigador recibe sus puntos de reputación.',
                variante: 'default',
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
        if (accion === 'rechazar') {
            if (motivoRechazo) data.motivo_rechazo = motivoRechazo;
            if (sancionar) {
                data.sancionar = '1';
                data.gravedad_sancion = gravedadSancion;
            }
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
        sancionar = false;
        gravedadSancion = 'leve';
        motivoRechazo = '';
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
                                {moderadoresAsignables.find((u) => String(u.id) === asignadoA)?.name ?? 'Seleccionar analista...'}
                            {/snippet}
                        </SelectTrigger>
                        <SelectContent>
                            {#each moderadoresAsignables as usuario (usuario.id)}
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
                    <Label for="reporte_duplicado_id">Informe original</Label>
                    {#if candidatosDuplicado.length === 0}
                        <p class="text-sm text-muted-foreground">
                            No hay otros informes enviados en este programa con los que compararlo.
                        </p>
                    {:else}
                        <select
                            id="reporte_duplicado_id"
                            bind:value={reporteDuplicadoId}
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Seleccionar el informe original...</option>
                            {#each candidatosDuplicado as candidato (candidato.id)}
                                <option value={String(candidato.id)}>
                                    {candidato.numero_reporte} · {candidato.titulo}
                                </option>
                            {/each}
                        </select>
                    {/if}
                </div>
            {/if}

            {#if accion === 'rechazar'}
                <div class="space-y-2">
                    <Label for="motivo_rechazo">Motivo estándar de rechazo</Label>
                    <select
                        id="motivo_rechazo"
                        bind:value={motivoRechazo}
                        onchange={alCambiarMotivoRechazo}
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Selecciona un motivo estándar...</option>
                        <option value="falso_positivo">Falso positivo / Escáner sin impacto</option>
                        <option value="fuera_de_alcance">Fuera del alcance del programa (Out-of-Scope)</option>
                        <option value="sin_impacto">Sin impacto demostrable (Informativo)</option>
                        <option value="falta_evidencia">Falta de evidencia o no reproducible</option>
                        <option value="otro">Otro motivo (especificar en la nota)</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={sancionar} />
                    <span>Aplicar sanción de reputación por reporte fraudulento/spam</span>
                </label>
                {#if sancionar}
                    <div class="space-y-2">
                        <Label for="gravedad_sancion">Gravedad de la sanción</Label>
                        <select id="gravedad_sancion" bind:value={gravedadSancion} class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="leve">Leve (-25 pts)</option>
                            <option value="media">Media (-80 pts, 7 días suspensión)</option>
                            <option value="grave">Grave (-250 pts, 30 días suspensión)</option>
                        </select>
                    </div>
                {/if}
            {/if}

            {#if accion !== 'validar' && accion !== 'cerrar'}
                <div class="space-y-2">
                    <Label for="nota">Nota {accion === 'pedir_info' ? '(requerida)' : (accion === 'rechazar' ? '(recomendada)' : '(opcional)')}</Label>
                    <textarea
                        id="nota"
                        bind:value={nota}
                        placeholder={accion === 'pedir_info' ? 'Describe qué información o evidencia adicional necesitas del investigador...' : 'Agrega una nota o justificacion...'}
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
                disabled={processing || (accion === 'asignar' && !asignadoA) || (accion === 'marcar_duplicado' && !reporteDuplicadoId) || (accion === 'pedir_info' && !nota.trim())}
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
