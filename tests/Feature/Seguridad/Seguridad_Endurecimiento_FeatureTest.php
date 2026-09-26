<?php

use App\Http\Middleware\LimitarRegistros;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * Pruebas de endurecimiento: superficie de ataque reducida (sin recuperar ni cambiar
 * contraseña, sin passkeys), validación estricta del registro, inyección SQL, XSS,
 * asignación masiva, límites de intentos y cabeceras HTTP de seguridad.
 */

const CLAVE_VALIDA = 'Clave-Segura-2026';

/** Datos de registro válidos, con lo que cada prueba quiera cambiar. */
function datosDeRegistro(array $cambios = []): array
{
    return [
        'name' => 'María José Pérez',
        'email' => 'maria@example.com',
        'password' => CLAVE_VALIDA,
        'password_confirmation' => CLAVE_VALIDA,
        'terminos' => '1',
        ...$cambios,
    ];
}

/** Payloads clásicos de inyección SQL. */
function payloadsSql(): array
{
    return [
        "' OR '1'='1",
        "' OR 1=1 --",
        "'; DROP TABLE users; --",
        '1 UNION SELECT password FROM users',
        "%' OR '%'='",
        '1; DELETE FROM reportes',
    ];
}

// ---------------------------------------------------------------------------
// Superficie de ataque: funciones retiradas
// ---------------------------------------------------------------------------

test('no existen las rutas para recuperar o cambiar la contraseña ni las de passkeys', function () {
    foreach (['password.request', 'password.email', 'password.reset', 'password.update', 'user-password.update', 'well-known.passkeys'] as $nombre) {
        expect(Route::has($nombre))->toBeFalse("La ruta [{$nombre}] no debería existir.");
    }

    $conPasskeys = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => str_contains(strtolower($ruta->uri()), 'passkey'));
    expect($conPasskeys)->toBeEmpty();

    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', ['email' => 'a@example.com'])->assertNotFound();
    $this->get('/reset-password/token-falso')->assertNotFound();
    $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.com', 'password' => CLAVE_VALIDA])->assertNotFound();
});

test('las paginas de acceso no ofrecen recuperar contraseña ni entrar con passkey', function () {
    $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->missing('canResetPassword'));

    $this->get(route('empresa.login'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('auth/EmpresaLogin')
        ->missing('canResetPassword'));
});

// ---------------------------------------------------------------------------
// Registro: validación estricta
// ---------------------------------------------------------------------------

test('el nombre no admite numeros, simbolos ni codigo', function (string $nombre) {
    $this->post(route('register.store'), datosDeRegistro(['name' => $nombre]))
        ->assertSessionHasErrors('name');

    $this->assertGuest();
    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
})->with([
    'con numeros' => 'Juan123',
    'solo numeros' => '123456',
    'inyeccion sql' => "Robert'); DROP TABLE users;--",
    'or 1=1' => "' OR '1'='1",
    'script' => '<script>alert(1)</script>',
    'simbolos' => 'Ana_Maria@',
    'dos espacios seguidos' => 'Ana  Maria',
    'una sola letra' => 'A',
    'demasiado largo' => str_repeat('a', 101),
]);

test('el nombre acepta letras con tildes, ñ, apostrofo y guion', function (string $nombre) {
    $this->post(route('register.store'), datosDeRegistro(['name' => $nombre]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'maria@example.com')->value('name'))->toBe($nombre);
})->with(['María José Pérez', "Ana O'Connor", 'Ana-Lucía Núñez', 'Ñandú Ibáñez']);

test('el correo se valida y se guarda en minusculas', function () {
    $this->post(route('register.store'), datosDeRegistro(['email' => 'no-es-un-correo']))
        ->assertSessionHasErrors('email');
    $this->post(route('register.store'), datosDeRegistro(['email' => "maria@example.com' OR '1'='1"]))
        ->assertSessionHasErrors('email');

    $this->post(route('register.store'), datosDeRegistro(['email' => 'Maria@Example.COM']))
        ->assertSessionHasNoErrors();
    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

test('no se puede registrar dos veces el mismo correo cambiando mayusculas', function () {
    User::factory()->create(['email' => 'maria@example.com']);

    $this->post(route('register.store'), datosDeRegistro(['email' => 'MARIA@example.com']))
        ->assertSessionHasErrors('email');
});

test('se rechazan las contraseñas debiles', function (string $clave) {
    $this->post(route('register.store'), datosDeRegistro(['password' => $clave, 'password_confirmation' => $clave]))
        ->assertSessionHasErrors('password');

    $this->assertGuest();
})->with([
    'password' => 'password',
    'solo numeros' => '12345678',
    'solo minusculas' => 'abcdefghij',
    'sin simbolo' => 'Abcdefg12',
    'sin mayuscula' => 'abcdef12-!',
    'corta' => 'Ab1-',
]);

test('el registro no permite asignarse rol, reputacion ni estado (asignacion masiva)', function () {
    $this->post(route('register.store'), datosDeRegistro([
        'roles' => ['administrador'],
        'rol' => 'administrador',
        'reputation_score' => 99999,
        'is_active' => false,
        'email_verified_at' => now()->toDateTimeString(),
    ]))->assertSessionHasNoErrors();

    $usuario = User::where('email', 'maria@example.com')->firstOrFail();
    expect($usuario->roles->pluck('slug')->all())->toBe(['investigador'])
        ->and((int) $usuario->reputation_score)->toBe(0)
        ->and($usuario->email_verified_at)->toBeNull();
});

test('el registro de empresa valida cada campo con su formato', function (string $campo, string $valor) {
    $this->post(route('empresa.register.store'), [
        'razon_social' => 'Acme Seguridad S.A.',
        'identificador_fiscal' => 'RFC-ACME-001',
        'empresa_email' => 'seguridad@acme.test',
        'name' => 'Ana Responsable',
        'email' => 'ana@acme.test',
        'password' => CLAVE_VALIDA,
        'password_confirmation' => CLAVE_VALIDA,
        'terminos' => '1',
        $campo => $valor,
    ])->assertSessionHasErrors($campo);

    expect(User::where('email', 'ana@acme.test')->exists())->toBeFalse();
})->with([
    'nombre con numeros' => ['name', 'Ana 2'],
    'razon social con codigo' => ['razon_social', '<script>alert(1)</script>'],
    'razon social con sql' => ['razon_social', "Acme'; DROP TABLE empresas;--"],
    'identificador con simbolos' => ['identificador_fiscal', "RFC' OR '1'='1"],
    'telefono con letras' => ['telefono', '555-HACK'],
    'sitio web javascript' => ['sitio_web', 'javascript:alert(1)'],
    'correo de empresa invalido' => ['empresa_email', 'no-es-correo'],
]);

test('el perfil tampoco admite numeros en el nombre', function () {
    $usuario = investigador(['name' => 'Ana Pérez']);

    $this->actingAs($usuario)
        ->patch(route('profile.update'), ['name' => 'Ana 1337', 'email' => $usuario->email])
        ->assertSessionHasErrors('name');

    expect($usuario->fresh()->name)->toBe('Ana Pérez');
});

// ---------------------------------------------------------------------------
// Inyección SQL
// ---------------------------------------------------------------------------

test('el login no se salta con inyeccion sql', function (string $payload) {
    User::factory()->create(['email' => 'victima@example.com']);

    $this->post(route('login.store'), ['email' => $payload, 'password' => $payload]);
    $this->post(route('login.store'), ['email' => 'victima@example.com', 'password' => $payload]);

    $this->assertGuest();
})->with(payloadsSql());

test('los buscadores y filtros tratan la inyeccion sql como texto y no filtran datos ajenos', function (string $payload) {
    $autor = investigador();
    $otro = investigador();
    $programa = Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]);
    reporteDe($autor, $programa, ['estado' => 'enviado', 'titulo' => 'Mi informe']);
    $ajeno = reporteDe($otro, $programa, ['estado' => 'enviado', 'titulo' => 'Informe ajeno']);
    $admin = administrador();
    $moderador = moderadorDe($programa);
    $usuarios = User::count();

    $this->actingAs($autor);
    foreach (['busqueda', 'estado', 'severidad', 'programa_id'] as $filtro) {
        $respuesta = $this->get(route('reportes.index', [$filtro => $payload]))->assertOk();
        $ids = collect($respuesta->inertiaProps()['reportes']['data'])->pluck('id');
        expect($ids)->not->toContain($ajeno->id);
    }
    $this->get(route('programas.index', ['busqueda' => $payload, 'estado' => $payload]))->assertOk();

    $this->actingAs($admin);
    $this->get(route('admin.auditoria', ['accion' => $payload, 'entidad' => $payload, 'rol' => $payload]))->assertOk();

    $this->actingAs($moderador);
    $this->get(route('moderacion.index', ['busqueda' => $payload]))->assertOk();

    // Nada se borró ni se alteró.
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('reportes'))->toBeTrue()
        ->and(User::count())->toBe($usuarios)
        ->and(Reporte::count())->toBe(2);
})->with(payloadsSql());

test('un titulo con sql se guarda literal, sin ejecutarse', function () {
    $autor = investigador();
    $programa = conObjetivo(Programa::factory()->create(['estado' => 'activo', 'es_publico' => true]));
    $titulo = "XSS'); DROP TABLE reportes; --";

    $this->actingAs($autor)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => $titulo,
        'descripcion' => 'Detalle',
    ])->assertRedirect();

    expect(Schema::hasTable('reportes'))->toBeTrue()
        ->and(Reporte::where('titulo', $titulo)->exists())->toBeTrue()
        ->and(DB::table('reportes')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// XSS y control de acceso
// ---------------------------------------------------------------------------

test('el contenido del usuario se entrega escapado: un script en el titulo no se ejecuta', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador', 'titulo' => '<script>alert("xss")</script>']);

    $html = $this->actingAs($autor)->get(route('reportes.show', $reporte))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert("xss")</script>');
});

test('un investigador no puede ver ni editar el informe de otro cambiando el id (IDOR)', function () {
    $ajeno = reporteDe(investigador(), null, ['estado' => 'borrador', 'titulo' => 'Secreto']);

    $this->actingAs(investigador());
    $this->get(route('reportes.show', $ajeno))->assertForbidden();
    $this->get(route('reportes.edit', $ajeno))->assertForbidden();
    $this->put(route('reportes.update', $ajeno), ['titulo' => 'Hackeado', 'descripcion' => 'x'])->assertForbidden();

    expect($ajeno->fresh()->titulo)->toBe('Secreto');
});

test('las areas de administracion rechazan a quien no es administrador', function () {
    $this->actingAs(investigador());

    $this->get(route('admin.auditoria'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Fuerza bruta y abuso
// ---------------------------------------------------------------------------

test('el login se bloquea tras varios intentos fallidos', function () {
    User::factory()->create(['email' => 'victima@example.com']);

    foreach (range(1, 5) as $intento) {
        $this->post(route('login.store'), ['email' => 'victima@example.com', 'password' => "mala-{$intento}"]);
    }

    $this->post(route('login.store'), ['email' => 'victima@example.com', 'password' => 'otra-mala'])
        ->assertStatus(429);
    $this->assertGuest();
});

test('no se pueden crear cuentas en masa desde la misma conexion', function () {
    foreach (range(1, LimitarRegistros::INTENTOS) as $i) {
        $this->post(route('register.store'), datosDeRegistro(['email' => "bot{$i}@example.com"]));
        auth()->guard('web')->logout();
    }

    $this->post(route('register.store'), datosDeRegistro(['email' => 'bot-extra@example.com']))
        ->assertStatus(429);

    expect(User::where('email', 'bot-extra@example.com')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Cabeceras HTTP
// ---------------------------------------------------------------------------

test('las respuestas llevan cabeceras de seguridad', function () {
    $respuesta = $this->get(route('login'))->assertOk();

    $respuesta->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By');

    expect($respuesta->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($respuesta->headers->get('Permissions-Policy'))->toContain('camera=()');
});

test('por https se exige https en adelante (HSTS)', function () {
    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
