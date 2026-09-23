<?php

use App\Enums\GravedadSancion;
use App\Models\Empresa;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use App\Notifications\AvisoPlataforma;
use App\Services\Reputacion\ReputationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** Los avisos de un usuario, del más reciente al más antiguo, ya en forma de datos. */
function avisos(User $usuario): array
{
    return $usuario->notifications()->latest()->get()->map(fn ($n) => $n->data)->all();
}

function titulos(User $usuario): array
{
    return array_column(avisos($usuario), 'titulo');
}

/**
 * Un programa con su empresa (propietario), un moderador asignado y un informe enviado por un investigador.
 *
 * @return array{programa: Programa, dueno: User, moderador: User, investigador: User, reporte: Reporte}
 */
function escenarioDeInforme(string $estado = 'enviado'): array
{
    $dueno = propietarioDeEmpresa();
    $programa = programaDeEmpresa($dueno);
    $moderador = moderadorDe($programa);
    $investigador = investigador();
    $reporte = reporteDe($investigador, $programa, ['estado' => $estado, 'asignado_a' => null]);

    return compact('programa', 'dueno', 'moderador', 'investigador', 'reporte');
}

// ---------------------------------------------------------------------------
// Informes
// ---------------------------------------------------------------------------

test('al enviar un informe avisan los moderadores del programa y al propietario de la empresa, no al autor ni a otros moderadores', function () {
    ['programa' => $programa, 'dueno' => $dueno, 'moderador' => $moderador, 'investigador' => $autor] = escenarioDeInforme('borrador');
    $ajeno = moderadorDe(Programa::factory()->create());
    $reporte = reporteDe($autor, $programa, [
        'estado' => 'borrador',
        'titulo' => 'XSS en login',
        'poc' => pocCifrado(['evidencia' => 'Evidencia de prueba.']),
    ]);

    $this->actingAs($autor)->post(route('reportes.enviar', $reporte))->assertRedirect();

    expect(titulos($moderador))->toContain('Nuevo informe recibido')
        ->and(titulos($dueno))->toContain('Nuevo informe recibido')
        ->and(avisos($moderador)[0]['url'])->toBe("/reportes/{$reporte->id}")
        ->and(avisos($moderador)[0]['mensaje'])->toContain('XSS en login')
        ->and(titulos($autor))->toBe([])
        ->and(titulos($ajeno))->toBe([]);
});

test('cada cambio de estado avisa al investigador, pero no a quien lo provoca', function () {
    ['moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();

    $this->actingAs($moderador)->post(route('reportes.revisar', $reporte))->assertRedirect();
    expect(titulos($autor))->toBe(['Tu informe está en revisión'])
        ->and(titulos($moderador))->toBe([]);

    $this->actingAs($moderador)->post(route('reportes.rechazar', $reporte), ['nota' => 'No aplica.'])->assertRedirect();
    expect(titulos($autor))->toContain('Tu informe está rechazado');
});

test('al validar un informe se avisa al investigador y la empresa dueña debe corregirlo', function () {
    ['dueno' => $dueno, 'moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();

    $this->actingAs($moderador)->post(route('reportes.validar', $reporte))->assertRedirect();

    expect(titulos($autor))->toContain('Tu informe está validado')
        ->and(titulos($dueno))->toContain('Informe validado: hay que corregirlo');
});

test('cuando la empresa pone el informe en reparacion y lo cierra, el investigador se entera', function () {
    ['dueno' => $dueno, 'moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();
    $this->actingAs($moderador)->post(route('reportes.validar', $reporte));

    $this->actingAs($dueno)->post(route('reportes.reparacion', $reporte))->assertRedirect();
    $this->actingAs($dueno)->post(route('reportes.cerrar', $reporte))->assertRedirect();

    expect(titulos($autor))->toContain('Tu informe está cerrado como resuelto')
        ->and(titulos($autor))->toContain('Tu informe está en reparación (la empresa lo está corrigiendo)');
});

test('marcar un informe como duplicado avisa a su autor', function () {
    ['programa' => $programa, 'moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();
    $original = reporteDe(investigador(), $programa, ['estado' => 'validado']);

    $this->actingAs($moderador)->post(route('reportes.marcar-duplicado', $reporte), ['reporte_duplicado_id' => $original->id])->assertRedirect();

    expect(titulos($autor))->toContain('Tu informe fue marcado como duplicado');
});

test('asignar un informe avisa solo al moderador asignado', function () {
    ['programa' => $programa, 'moderador' => $moderador, 'reporte' => $reporte] = escenarioDeInforme();
    $otroModerador = moderadorDe($programa);

    $this->actingAs($moderador)->post(route('reportes.asignar', $reporte), ['asignado_a' => $otroModerador->id])->assertRedirect();

    expect(titulos($otroModerador))->toBe(['Se te asignó un informe'])
        ->and(titulos($moderador))->toBe([]);
});

test('un comentario avisa a los demas participantes pero no a quien comenta', function () {
    ['dueno' => $dueno, 'moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();
    $reporte->update(['asignado_a' => $moderador->id]);

    $this->actingAs($moderador)->post(route('reportes.comentar', $reporte), ['nota' => '¿Puedes dar más detalle?'])->assertRedirect();

    expect(titulos($autor))->toContain('Nuevo comentario en un informe')
        ->and(titulos($dueno))->toContain('Nuevo comentario en un informe')
        ->and(titulos($moderador))->toBe([]);
});

// ---------------------------------------------------------------------------
// Sanciones, apelaciones y reputación
// ---------------------------------------------------------------------------

test('una sancion avisa al sancionado con los puntos, la suspension y el plazo para apelar', function () {
    ['moderador' => $moderador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();

    $this->actingAs($moderador)->post(route('reportes.rechazar', $reporte), [
        'nota' => 'Fabricado.', 'sancionar' => true, 'gravedad_sancion' => 'media',
    ])->assertRedirect();

    $aviso = collect(avisos($autor))->firstWhere('titulo', 'Se te aplicó una sanción');
    expect($aviso['tipo'])->toBe('sancion')
        ->and($aviso['mensaje'])->toContain('Sanción media')->toContain('puntos')->toContain('Suspendido hasta')->toContain('Puedes apelar hasta')
        ->and($aviso['url'])->toBe('/reputacion/sanciones');
});

test('una apelacion nueva avisa a otros moderadores y a los administradores, no al que sanciono ni al apelante', function () {
    ['moderador' => $sancionador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();
    $otroModerador = moderador();
    $admin = administrador();
    $this->actingAs($sancionador)->post(route('reportes.rechazar', $reporte), ['nota' => 'x', 'sancionar' => true, 'gravedad_sancion' => 'leve']);
    $sancion = $autor->sanciones()->firstOrFail();

    $this->actingAs($autor)->post(route('reputacion.apelar', $sancion), ['motivo' => 'Injusto.'])->assertRedirect();

    expect(titulos($otroModerador))->toContain('Nueva apelación pendiente')
        ->and(titulos($admin))->toContain('Nueva apelación pendiente')
        ->and(titulos($sancionador))->not->toContain('Nueva apelación pendiente')
        ->and(titulos($autor))->not->toContain('Nueva apelación pendiente');
});

test('al resolver la apelacion se avisa al apelante y a quien aplico la sancion, sin duplicar el aviso de revocacion', function () {
    ['moderador' => $sancionador, 'investigador' => $autor, 'reporte' => $reporte] = escenarioDeInforme();
    $this->actingAs($sancionador)->post(route('reportes.rechazar', $reporte), ['nota' => 'x', 'sancionar' => true, 'gravedad_sancion' => 'leve']);
    $sancion = $autor->sanciones()->firstOrFail();
    $this->actingAs($autor)->post(route('reputacion.apelar', $sancion), ['motivo' => 'Injusto.']);
    $apelacion = $sancion->apelaciones()->firstOrFail();

    $this->actingAs(moderador())->post(route('apelaciones.resolver', $apelacion), ['aprobada' => true, 'nota' => 'Procede.'])->assertRedirect();

    expect(titulos($autor))->toContain('Tu apelación fue aprobada')
        ->and(titulos($autor))->not->toContain('Se revocó tu sanción')
        ->and(titulos($sancionador))->toContain('Apelación aprobada');
    expect(collect(avisos($autor))->firstWhere('titulo', 'Tu apelación fue aprobada')['url'])->toBe("/reputacion/apelaciones/{$apelacion->id}");
});

test('revocar una sancion directamente avisa al sancionado una sola vez', function () {
    $investigador = investigador();
    $sancion = app(ReputationService::class)->aplicarSancion($investigador, 'falso_positivo', GravedadSancion::Leve);

    $this->actingAs(administrador())->post(route('admin.sanciones.revocar', $sancion), ['nota' => 'Error nuestro.'])->assertRedirect();

    expect(array_count_values(titulos($investigador))['Se revocó tu sanción'])->toBe(1);
});

test('subir o bajar de rango avisa una sola vez por cambio', function () {
    $investigador = investigador();
    $servicio = app(ReputationService::class);

    $servicio->asentar($investigador->id, 60, 'bonus');   // 60: sigue en Bronce
    expect(titulos($investigador))->toBe([]);

    $this->travel(1)->seconds();
    $servicio->asentar($investigador->id, 60, 'bonus');   // 120: sube a Plata (100)
    expect(titulos($investigador))->toBe(['Subiste al rango Plata']);

    $this->travel(1)->seconds();
    $servicio->asentar($investigador->id, 10, 'bonus');   // 130: mismo rango, sin aviso
    expect(titulos($investigador))->toHaveCount(1);

    $this->travel(1)->seconds();
    $servicio->asentar($investigador->id, -50, 'castigo'); // 80: baja a Bronce
    expect(titulos($investigador)[0])->toBe('Bajaste al rango Bronce');
});

// ---------------------------------------------------------------------------
// Empresas y moderación
// ---------------------------------------------------------------------------

test('registrar una empresa avisa a los administradores', function () {
    $admin = administrador();
    $otroUsuario = investigador();

    $this->post(route('empresa.register.store'), [
        'razon_social' => 'Acme Seguridad S.A.',
        'identificador_fiscal' => 'RFC-ACME-777',
        'empresa_email' => 'seguridad@acme.test',
        'name' => 'Ana Responsable',
        'email' => 'ana@acme.test',
        'password' => 'Password123!Password',
        'password_confirmation' => 'Password123!Password',
    ])->assertRedirect(route('empresa.dashboard'));

    $aviso = avisos($admin)[0];
    expect($aviso['titulo'])->toBe('Empresa pendiente de aprobación')
        ->and($aviso['mensaje'])->toContain('Acme Seguridad S.A.')
        ->and($aviso['url'])->toBe('/admin/empresas')
        ->and(titulos($otroUsuario))->toBe([]);
});

test('las decisiones del administrador sobre una empresa avisan a su propietario', function () {
    $admin = administrador();
    $empresa = Empresa::factory()->create();
    $propietario = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($propietario, ['rol_interno' => 'propietario', 'estado' => 'activo']);
    $this->actingAs($admin);

    $this->post(route('admin.empresas.aprobar', $empresa))->assertRedirect();
    $this->post(route('admin.empresas.suspender', $empresa), ['motivo' => 'Datos falsos.'])->assertRedirect();
    $this->post(route('admin.empresas.reactivar', $empresa))->assertRedirect();

    expect(titulos($propietario))->toEqualCanonicalizing(['Tu empresa fue aprobada', 'Tu empresa fue suspendida', 'Tu empresa fue aprobada']);
});

test('asignar o retirar a un moderador de un programa o del rol le avisa', function () {
    $admin = administrador();
    $usuario = investigador();
    $programa = Programa::factory()->create(['nombre' => 'Programa Acme']);
    $this->actingAs($admin);

    $this->post(route('admin.moderadores.asignar', $usuario))->assertRedirect();
    $this->post(route('admin.programas.moderadores.asignar', [$programa, $usuario]))->assertRedirect();
    $this->delete(route('admin.programas.moderadores.revocar', [$programa, $usuario]))->assertRedirect();
    $this->delete(route('admin.moderadores.revocar', $usuario))->assertRedirect();

    expect(titulos($usuario))->toEqualCanonicalizing(['Ya no eres moderador', 'Te retiraron de un programa', 'Se te asignó un programa', 'Ahora eres moderador']);
});

// ---------------------------------------------------------------------------
// La campana
// ---------------------------------------------------------------------------

test('la campana comparte cuantos avisos hay sin leer y los seis ultimos', function () {
    $usuario = investigador();
    foreach (range(1, 8) as $i) {
        $usuario->notify(new AvisoPlataforma('informe', "Aviso {$i}", 'texto', '/dashboard'));
    }
    $usuario->notifications()->first()->markAsRead();

    $this->actingAs($usuario)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('notificaciones.no_leidas', 7)
        ->has('notificaciones.recientes', 6));
});

test('abrir un aviso lo marca como leido y lleva a su destino', function () {
    $usuario = investigador();
    $usuario->notify(new AvisoPlataforma('informe', 'Aviso', 'texto', '/reputacion'));
    $aviso = $usuario->notifications()->firstOrFail();

    $this->actingAs($usuario)->get(route('notificaciones.abrir', $aviso->id))->assertRedirect('/reputacion');

    expect($aviso->fresh()->read_at)->not->toBeNull();
});

test('un aviso nunca redirige fuera de la plataforma', function (string $url) {
    $usuario = investigador();
    $usuario->notify(new AvisoPlataforma('informe', 'Aviso', 'texto', $url));

    $this->actingAs($usuario)->get(route('notificaciones.abrir', $usuario->notifications()->firstOrFail()->id))
        ->assertRedirect(route('notificaciones.index'));
})->with(['https://malo.example/robo', '//malo.example/robo', 'javascript:alert(1)']);

test('nadie abre ni marca los avisos de otro usuario', function () {
    $dueno = investigador();
    $dueno->notify(new AvisoPlataforma('informe', 'Privado', 'texto', '/dashboard'));
    $aviso = $dueno->notifications()->firstOrFail();

    $this->actingAs(investigador())->get(route('notificaciones.abrir', $aviso->id))->assertNotFound();
    $this->actingAs(investigador())->post(route('notificaciones.leer', $aviso->id))->assertNotFound();
    expect($aviso->fresh()->read_at)->toBeNull();
});

test('se puede marcar uno o todos como leidos y filtrar los no leidos', function () {
    $usuario = investigador();
    foreach (['A', 'B', 'C'] as $letra) {
        $usuario->notify(new AvisoPlataforma('informe', "Aviso {$letra}", 'texto', '/dashboard'));
    }
    $this->actingAs($usuario);

    $primero = $usuario->notifications()->latest()->first();
    $this->post(route('notificaciones.leer', $primero->id))->assertRedirect();
    expect($usuario->unreadNotifications()->count())->toBe(2);

    $this->get(route('notificaciones.index', ['filtro' => 'no_leidas']))
        ->assertInertia(fn ($page) => $page->component('notificaciones/Index')->has('avisos.data', 2)->where('filtro', 'no_leidas'));

    $this->post(route('notificaciones.leer-todas'))->assertRedirect();
    expect($usuario->unreadNotifications()->count())->toBe(0);
    $this->get(route('notificaciones.index'))->assertInertia(fn ($page) => $page->has('avisos.data', 3));
});

test('una empresa aun pendiente de aprobacion puede ver sus avisos', function () {
    $empresa = Empresa::factory()->create();
    $propietario = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($propietario, ['rol_interno' => 'propietario', 'estado' => 'activo']);

    $this->actingAs($propietario)->get(route('notificaciones.index'))->assertOk();
});

test('los invitados no ven la campana', function () {
    $this->get(route('notificaciones.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Robustez y alcance
// ---------------------------------------------------------------------------

test('si falla el envio de un aviso la accion original sigue funcionando', function () {
    ['moderador' => $moderador, 'reporte' => $reporte] = escenarioDeInforme();
    Notification::swap(new class
    {
        public function send(): void
        {
            throw new RuntimeException('el aviso falló');
        }
    });

    $this->actingAs($moderador)->post(route('reportes.validar', $reporte))->assertRedirect();

    expect($reporte->fresh()->estado->value)->toBe('validado');
});

test('los avisos son solo internos: no se envia ningun correo', function () {
    Mail::fake();
    ['moderador' => $moderador, 'reporte' => $reporte] = escenarioDeInforme();

    $this->actingAs($moderador)->post(route('reportes.validar', $reporte))->assertRedirect();

    Mail::assertNothingSent();
    expect((new AvisoPlataforma('informe', 't', 'm'))->via(investigador()))->toBe(['database'])
        ->and(DB::table('notifications')->count())->toBeGreaterThan(0);
});
