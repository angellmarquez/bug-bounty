<?php

use App\Abac\AbacEngine;
use App\Enums\EstadoPrograma;
use App\Enums\EstadoReporte;
use App\Models\Apelacion;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Matriz ABAC
|--------------------------------------------------------------------------
|
| Cubre la política declarada en config/abac.php combinando rol × acción ×
| atributos de objeto/entorno. Cada fila devuelve [descripción, acción,
| escenario (cierra usuario y objeto), decisión esperada].
|
*/

/**
 * Un moderador asignado a un programa y un informe de ese programa.
 * `asignado_a => 'yo'` deja el informe asignado al propio moderador.
 *
 * @param  array<string, mixed>  $atributos  atributos del informe
 * @param  string|array<int, string>  $roles  roles del moderador
 * @return array{0: User, 1: Reporte}
 */
function reporteModerado(array $atributos = [], string|array $roles = 'moderador'): array
{
    $programa = Programa::factory()->create();
    $moderador = conRol(User::factory()->create(), $roles);
    $moderador->programasModerados()->attach($programa->id);

    if (($atributos['asignado_a'] ?? null) === 'yo') {
        $atributos['asignado_a'] = $moderador->id;
    }

    return [$moderador, reporteDe(investigador(), $programa, $atributos)];
}

dataset('matriz_abac', [
    // ------------------------------------------------------------------
    // Reportes — investigador
    // ------------------------------------------------------------------
    'inv ve su propio reporte' => ['reportes.ver', fn () => [($inv = investigador()), reporteDe($inv)], true],
    'inv edita su reporte borrador' => ['reportes.editar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::Borrador->value])], true],
    'inv edita su reporte enviado' => ['reportes.editar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::Enviado->value])], true],
    'inv envía su reporte borrador' => ['reportes.enviar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::Borrador->value])], true],
    'inv elimina su reporte borrador' => ['reportes.eliminar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::Borrador->value])], true],
    'inv no ve el reporte de otro investigador' => ['reportes.ver', fn () => [investigador(), reporteDe(investigador())], false],
    'inv no ve notas internas ajenas' => ['reportes.ver_notas_internas', fn () => [investigador(), reporteDe(investigador())], false],
    'inv no edita su reporte en revisión' => ['reportes.editar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::EnRevision->value])], false],
    'inv no elimina su reporte enviado' => ['reportes.eliminar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::Enviado->value])], false],
    'inv no puede triajar (deny explícito gana)' => ['reportes.validar', fn () => [($inv = investigador()), reporteDe($inv, atributos: ['estado' => EstadoReporte::EnRevision->value])], false],
    'inv no puede asignar reportes' => ['reportes.asignar', fn () => [investigador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Enviado->value])], false],
    'inv no puede cerrar reportes' => ['reportes.cerrar', fn () => [investigador(), reporteDe(investigador())], false],
    'inv no opera sanciones fuera del alcance' => ['reportes.marcar_en_reparacion', fn () => [investigador(), reporteDe(investigador())], false],

    // ------------------------------------------------------------------
    // Reportes — moderador (triaje, solo en los programas que se le asignan)
    // ------------------------------------------------------------------
    'moderador ve un reporte enviado de su programa' => ['reportes.ver', fn () => reporteModerado(['estado' => EstadoReporte::Enviado->value]), true],
    'moderador ve notas internas de un reporte en revisión de su programa' => ['reportes.ver_notas_internas', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value]), true],
    'moderador no ve los borradores' => ['reportes.ver', fn () => reporteModerado(['estado' => EstadoReporte::Borrador->value]), false],
    'moderador no ve reportes de un programa que no modera' => ['reportes.ver', fn () => [moderador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Enviado->value])], false],
    'moderador asigna un reporte enviado sin asignar' => ['reportes.asignar', fn () => reporteModerado(['estado' => EstadoReporte::Enviado->value, 'asignado_a' => null]), true],
    'moderador asigna un reporte en revisión sin asignar' => ['reportes.asignar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null]), true],
    'moderador asigna un reporte validado sin asignar' => ['reportes.asignar', fn () => reporteModerado(['estado' => EstadoReporte::Validado->value, 'asignado_a' => null]), true],
    'moderador no reasigna un reporte que ya tiene responsable' => ['reportes.asignar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => moderador()->id]), false],
    'moderador no asigna un borrador' => ['reportes.asignar', fn () => reporteModerado(['estado' => EstadoReporte::Borrador->value, 'asignado_a' => null]), false],
    'moderador valida un reporte sin asignar' => ['reportes.validar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null]), true],
    'moderador valida un reporte asignado a sí mismo' => ['reportes.validar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => 'yo']), true],
    'moderador no valida un reporte asignado a otro moderador' => ['reportes.validar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => moderador()->id]), false],
    'moderador no valida un reporte borrador' => ['reportes.validar', fn () => reporteModerado(['estado' => EstadoReporte::Borrador->value]), false],
    'moderador no valida un reporte de un programa que no modera' => ['reportes.validar', fn () => [moderador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null])], false],
    'moderador marca duplicado un reporte en revisión' => ['reportes.marcar_duplicado', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null]), true],
    'moderador no cierra un reporte en reparación (lo cierra la empresa)' => ['reportes.cerrar', fn () => reporteModerado(['estado' => EstadoReporte::EnReparacion->value, 'asignado_a' => null]), false],
    'inv no interviene en el triaje de un reporte asignado' => ['reportes.validar', fn () => [($inv = investigador()), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => $inv->id])], false],

    // ------------------------------------------------------------------
    // Reportes — administrador: ve y audita, pero no participa en el día a
    // día (eso es del moderador/empresa). No triaja ni crea/envía/edita.
    // ------------------------------------------------------------------
    'admin ve cualquier reporte' => ['reportes.ver', fn () => [administrador(), reporteDe(investigador())], true],
    'admin ve notas internas de un borrador' => ['reportes.ver_notas_internas', fn () => [administrador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Borrador->value])], true],
    'admin no valida reportes: eso es del moderador/empresa' => ['reportes.validar', fn () => [administrador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value])], false],
    'admin no cierra reportes: eso es del moderador/empresa' => ['reportes.cerrar', fn () => [administrador(), reporteDe(investigador())], false],
    'admin no crea reportes' => ['reportes.crear', fn () => [administrador(), programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value])], false],
    'admin no ve la cola de moderación' => ['moderacion.ver', fn () => [administrador(), null], false],

    // ------------------------------------------------------------------
    // Programas
    // ------------------------------------------------------------------
    'inv ve un programa público activo' => ['programas.ver', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value, 'es_publico' => true])], true],
    'inv ve un programa público en pausa' => ['programas.ver', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::EnPausa->value, 'es_publico' => true])], true],
    'inv no ve un programa privado' => ['programas.ver', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value, 'es_publico' => false])], false],
    'inv no ve un programa archivado' => ['programas.ver', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::Archivado->value, 'es_publico' => true])], false],
    'inv con rango suficiente (plata) ve programa de nivel medio' => ['programas.ver', fn () => [investigador(['reputation_score' => 100]), programaDe(investigador(['reputation_score' => 100]), ['estado' => EstadoPrograma::Activo->value, 'es_publico' => true, 'nivel_acceso' => 'medio'])], true],
    'inv con rango insuficiente (bronce) no ve programa de nivel medio' => ['programas.ver', fn () => [investigador(['reputation_score' => 99]), programaDe(investigador(['reputation_score' => 99]), ['estado' => EstadoPrograma::Activo->value, 'es_publico' => true, 'nivel_acceso' => 'medio'])], false],
    'inv crea un reporte en un programa activo' => ['reportes.crear', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::Activo->value])], true],
    'inv no crea reportes en un programa borrador' => ['reportes.crear', fn () => [investigador(), programaDe(investigador(), ['estado' => EstadoPrograma::Borrador->value])], false],
    'moderador no crea programas' => ['programas.crear', fn () => [moderador(), null], false],
    'inv no crea programas' => ['programas.crear', fn () => [investigador(), null], false],
    'moderador no gestiona un programa' => ['programas.gestionar', fn () => [moderador(), programaDe(investigador())], false],
    'moderador no cambia el estado de un programa' => ['programas.cambiar_estado', fn () => [moderador(), programaDe(investigador())], false],
    'admin gestiona cualquier programa' => ['programas.gestionar', fn () => [administrador(), programaDe(investigador())], true],
    'inv no elimina programas' => ['programas.eliminar', fn () => [investigador(), programaDe(investigador())], false],
    'moderador no elimina programas' => ['programas.eliminar', fn () => [moderador(), programaDe(investigador())], false],
    'admin no elimina programas: lo decide la empresa dueña' => ['programas.eliminar', fn () => [administrador(), programaDe(investigador())], false],

    // ------------------------------------------------------------------
    // Claves PGP
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // Apelaciones
    // ------------------------------------------------------------------
    'inv apela su sanción aplicada en plazo' => ['apelaciones.crear', fn () => [($inv = investigador()), apelacionDe($inv)], true],
    'inv no apela la sanción de otro' => ['apelaciones.crear', fn () => [investigador(), apelacionDe(investigador())], false],
    'inv no apela su sanción fuera de plazo' => ['apelaciones.crear', fn () => [($inv = investigador()), apelacionDe($inv, ['plazo_apelacion' => now()->subDay()])], false],
    'inv no apela su sanción revocada' => ['apelaciones.crear', fn () => [($inv = investigador()), apelacionDe($inv, ['estado' => 'revocada'])], false],
    'inv no resuelve apelaciones' => ['apelaciones.resolver', fn () => [investigador(), apelacionDe(investigador())], false],
    'moderador resuelve apelaciones' => ['apelaciones.resolver', fn () => [moderador(), apelacionDe(investigador())], true],
    'admin resuelve cualquier apelación' => ['apelaciones.resolver', fn () => [administrador(), apelacionDe(investigador())], true],

    // ------------------------------------------------------------------
    // Invitado y multi-rol
    // ------------------------------------------------------------------
    'invitado no ve reportes' => ['reportes.ver', fn () => [null, reporteDe(investigador())], false],
    'invitado no ve programas públicos' => ['programas.ver', fn () => [null, programaDe(investigador())], false],
    'un usuario investigador+moderador triaja en el programa que modera' => ['reportes.validar', fn () => reporteModerado(['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null], ['investigador', 'moderador']), true],
    'un usuario investigador+moderador sigue sin ver borradores ajenos' => ['reportes.ver', fn () => reporteModerado(['estado' => EstadoReporte::Borrador->value], ['investigador', 'moderador']), false],
]);

/**
 * Construye una apelación del investigador sobre una sanción propia.
 *
 * @param  array<string, mixed>  $atributos  atributos de la sanción
 */
function apelacionDe(User $investigador, array $atributos = []): Apelacion
{
    $sancion = Sancion::factory()->create([
        'usuario_id' => $investigador->id,
        'estado' => 'aplicada',
        'plazo_apelacion' => now()->addDays(3),
        ...$atributos,
    ]);

    return Apelacion::factory()->create([
        'sancion_id' => $sancion->id,
        'usuario_id' => $investigador->id,
    ]);
}

test('matriz ABAC: la decisión coincide con la política declarada', function (
    string $accion,
    Closure $escenario,
    bool $esperado,
) {
    [$usuario, $objeto] = $escenario();

    $decision = app(AbacEngine::class)->evaluar($accion, $objeto, $usuario);

    expect($decision->estaPermitida())->toBe($esperado, sprintf(
        'Se esperaba %s para [%s]. %s',
        $esperado ? 'permitir' : 'denegar',
        $accion,
        $decision->motivo(),
    ));
})->with('matriz_abac');
