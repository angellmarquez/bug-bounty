<?php

use App\Enums\EstadoReporte;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Registra una ruta de prueba dentro del grupo `web` para que el route model
 * binding resuelva {reporte} antes de pasar por el middleware ABAC.
 */
function rutaAbac(string $accion, string $parametro = 'reporte'): void
{
    Route::middleware('web')
        ->get("/abac-prueba/{{$parametro}}", fn (Reporte $reporte) => 'acceso-ok')
        ->middleware(['auth', "abac:{$accion},{$parametro}"]);
}

test('el middleware abac permite el acceso autorizado', function () {
    rutaAbac('reportes.ver');

    $inv = investigador();
    $reporte = reporteDe($inv);

    $this->actingAs($inv)
        ->get("/abac-prueba/{$reporte->id}")
        ->assertOk()
        ->assertSee('acceso-ok');
});

test('el middleware abac permite triaje a gestión con reporte sin asignar', function () {
    rutaAbac('reportes.validar');

    $ges = gestion();
    $reporte = reporteDe(investigador(), atributos: [
        'estado' => EstadoReporte::EnRevision->value,
        'asignado_a' => null,
    ]);

    $this->actingAs($ges)
        ->get("/abac-prueba/{$reporte->id}")
        ->assertOk();
});

test('el middleware abac deniega con 403 y registra la auditoría abac.denegado', function () {
    rutaAbac('reportes.asignar');

    $inv = investigador();
    $reporte = reporteDe(investigador(), atributos: ['estado' => EstadoReporte::Enviado->value]);

    $this->actingAs($inv)
        ->get("/abac-prueba/{$reporte->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $inv->id,
        'accion' => 'abac.denegado',
        'entidad_type' => Reporte::class,
        'entidad_id' => $reporte->id,
    ]);
});

test('el middleware abac deniega a un invitado aunque el objeto esté ligado', function () {
    Route::middleware('web')
        ->get('/abac-prueba/{reporte}', fn (Reporte $reporte) => 'acceso-ok')
        ->middleware('abac:reportes.ver,reporte');

    $reporte = reporteDe(investigador());

    $this->get("/abac-prueba/{$reporte->id}")->assertForbidden();
});

test('un usuario sin rol no accede aunque la acción exista', function () {
    rutaAbac('reportes.ver');

    $sinRol = User::factory()->create();
    $reporte = reporteDe(investigador());

    $this->actingAs($sinRol)
        ->get("/abac-prueba/{$reporte->id}")
        ->assertForbidden();
});
