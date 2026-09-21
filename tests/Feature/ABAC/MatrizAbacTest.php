<?php

use App\Abac\AbacEngine;
use App\Enums\EstadoPrograma;
use App\Enums\EstadoReporte;
use App\Models\Apelacion;
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
    // Reportes — gestión (triaje)
    // ------------------------------------------------------------------
    'gestion ve un reporte enviado' => ['reportes.ver', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Enviado->value])], true],
    'gestion ve notas internas de un reporte en revisión' => ['reportes.ver_notas_internas', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value])], true],
    'gestion no ve los borradores' => ['reportes.ver', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Borrador->value])], false],
    'gestion asigna un reporte enviado' => ['reportes.asignar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Enviado->value])], true],
    'gestion asigna un reporte en revisión' => ['reportes.asignar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value])], true],
    'gestion no asigna un reporte validado' => ['reportes.asignar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Validado->value])], false],
    'gestion valida un reporte sin asignar' => ['reportes.validar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null])], true],
    'gestion valida un reporte asignado a sí mismo' => ['reportes.validar', fn () => [($ges = gestion()), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => $ges->id])], true],
    'gestion no valida un reporte asignado a otro gestor' => ['reportes.validar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => gestion()->id])], false],
    'gestion no valida un reporte borrador' => ['reportes.validar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Borrador->value])], false],
    'gestion marca duplicado un reporte en revisión' => ['reportes.marcar_duplicado', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value])], true],
    'gestion marca en reparación un reporte validado sin adjudicar' => ['reportes.marcar_en_reparacion', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Validado->value, 'asignado_a' => null])], true],
    'gestion cierra un reporte en reparación' => ['reportes.cerrar', fn () => [gestion(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnReparacion->value, 'asignado_a' => null])], true],
    'inv no interviene en el triaje de un reporte asignado' => ['reportes.validar', fn () => [($inv = investigador()), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => $inv->id])], false],

    // ------------------------------------------------------------------
    // Reportes — administrador (bypass)
    // ------------------------------------------------------------------
    'admin ve cualquier reporte' => ['reportes.ver', fn () => [administrador(), reporteDe(investigador())], true],
    'admin ve notas internas de un borrador' => ['reportes.ver_notas_internas', fn () => [administrador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Borrador->value])], true],
    'admin valida cualquier reporte' => ['reportes.validar', fn () => [administrador(), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value])], true],
    'admin cierra reportes sin asignar' => ['reportes.cerrar', fn () => [administrador(), reporteDe(investigador())], true],

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
    'gestion crea programas' => ['programas.crear', fn () => [gestion(), null], true],
    'inv no crea programas' => ['programas.crear', fn () => [investigador(), null], false],
    'gestion gestiona un programa que creó' => ['programas.gestionar', fn () => [($ges = gestion()), programaDe($ges)], true],
    'gestion no gestiona el programa de otro gestor' => ['programas.gestionar', fn () => [gestion(), programaDe(gestion())], false],
    'gestion cambia el estado de su programa' => ['programas.cambiar_estado', fn () => [($ges = gestion()), programaDe($ges)], true],
    'gestion no cambia el estado del programa ajeno' => ['programas.cambiar_estado', fn () => [gestion(), programaDe(gestion())], false],
    'admin gestiona cualquier programa' => ['programas.gestionar', fn () => [administrador(), programaDe(gestion())], true],
    'inv no elimina programas' => ['programas.eliminar', fn () => [investigador(), programaDe(investigador())], false],
    'admin elimina programas (soft delete)' => ['programas.eliminar', fn () => [administrador(), programaDe(gestion())], true],

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
    'gestion resuelve apelaciones' => ['apelaciones.resolver', fn () => [gestion(), apelacionDe(investigador())], true],
    'admin resuelve cualquier apelación' => ['apelaciones.resolver', fn () => [administrador(), apelacionDe(investigador())], true],

    // ------------------------------------------------------------------
    // Invitado y multi-rol
    // ------------------------------------------------------------------
    'invitado no ve reportes' => ['reportes.ver', fn () => [null, reporteDe(investigador())], false],
    'invitado no ve programas públicos' => ['programas.ver', fn () => [null, programaDe(investigador())], false],
    'un usuario con roles investigador+gestion triajea sin asignar' => ['reportes.validar', fn () => [($mix = conRol(investigador(), ['investigador', 'gestion'])), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::EnRevision->value, 'asignado_a' => null])], true],
    'un usuario con roles investigador+gestion sigue sin ver borradores ajenos' => ['reportes.ver', fn () => [($mix = conRol(investigador(), ['investigador', 'gestion'])), reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Borrador->value])], false],
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
