<?php

use App\Enums\GravedadSancion;
use App\Models\Adjunto;
use App\Models\Apelacion;
use App\Models\ApelacionEvento;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\Adjuntos\AdjuntoService;
use App\Services\Pgp\PgpService;
use App\Services\Reputacion\ReputationService;
use App\Services\Reputacion\TrazaApelaciones;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['adjuntos.disco' => 'local']);
});

function fotoPng(string $nombre = 'captura.png', string $extra = ''): UploadedFile
{
    $imagen = imagecreatetruecolor(40, 30);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 20, 200, 120));
    ob_start();
    imagepng($imagen);
    $binario = ob_get_clean().$extra;

    return UploadedFile::fake()->createWithContent($nombre, $binario);
}

function fotoDe(Reporte $reporte, User $autor): Adjunto
{
    return app(AdjuntoService::class)->guardar($reporte, [fotoPng()], $autor)->first();
}

function sancionParaApelar(User $sancionado): Sancion
{
    return app(ReputationService::class)->aplicarSancion($sancionado, 'falso_positivo', GravedadSancion::Leve, null, [], null, moderador());
}

// ---------------------------------------------------------------------------
// Informes: subir
// ---------------------------------------------------------------------------

test('el investigador crea un informe con fotos: quedan cifradas en el disco y con su huella', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create();

    $this->actingAs($investigador)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'XSS con capturas',
        'descripcion' => 'Descripción del hallazgo con capturas de pantalla adjuntas.',
        'poc' => json_encode(['pasos' => 'abrir y pegar']),
        'fotos' => [fotoPng('uno.png'), fotoPng('dos.png')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $reporte = Reporte::where('titulo', 'XSS con capturas')->firstOrFail();
    $fotos = $reporte->adjuntos;

    expect($fotos)->toHaveCount(2)
        ->and($fotos[0]->nombre_original)->toBe('uno.png')
        ->and($fotos[0]->mime)->toBe('image/png')
        ->and($fotos[0]->ancho)->toBe(40);

    // En el disco no hay una imagen: hay un bloque PGP.
    $enDisco = Storage::disk('local')->get($fotos[0]->ruta);
    expect($enDisco)->toContain('PGP MESSAGE-----')
        ->and(str_contains($enDisco, "\x89PNG"))->toBeFalse();

    // Al descifrarla, la imagen coincide con la huella guardada.
    $binario = app(PgpService::class)->descifrarArchivo($enDisco);
    expect(hash('sha256', $binario))->toBe($fotos[0]->sha256);

    expect(Auditoria::where('accion', 'adjuntos.subidos')->where('entidad_id', $reporte->id)->exists())->toBeTrue();
});

test('la foto se vuelve a codificar: lo que venía pegado tras la imagen desaparece', function () {
    $investigador = investigador();
    $reporte = reporteDe($investigador, null, ['estado' => 'borrador']);

    $this->actingAs($investigador)->post(route('reportes.fotos.subir', $reporte), [
        'fotos' => [fotoPng('poliglota.png', '<script>alert("xss")</script>MARCADOR-OCULTO')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $respuesta = $this->get(route('reportes.fotos.ver', [$reporte, $reporte->adjuntos()->firstOrFail()]));

    $respuesta->assertOk();
    expect($respuesta->getContent())->not->toContain('MARCADOR-OCULTO')
        ->and($respuesta->getContent())->not->toContain('<script>')
        ->and(getimagesizefromstring($respuesta->getContent())['mime'])->toBe('image/png');
});

test('se rechazan archivos que no son fotos aunque tengan extensión de imagen', function (UploadedFile $archivo) {
    $investigador = investigador();
    $reporte = reporteDe($investigador, null, ['estado' => 'borrador']);

    $this->actingAs($investigador)
        ->post(route('reportes.fotos.subir', $reporte), ['fotos' => [$archivo]])
        ->assertSessionHasErrors();

    expect($reporte->adjuntos()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'texto con extensión png' => fn () => UploadedFile::fake()->createWithContent('falsa.png', 'esto no es una imagen'),
    'svg (puede llevar scripts)' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'php disfrazado' => fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>'),
    'gif (no admitido)' => fn () => UploadedFile::fake()->image('animada.gif', 10, 10),
]);

test('se rechaza una foto que supera el peso máximo', function () {
    config(['adjuntos.max_kb' => 1]);
    $investigador = investigador();
    $reporte = reporteDe($investigador, null, ['estado' => 'borrador']);

    $this->actingAs($investigador)
        ->post(route('reportes.fotos.subir', $reporte), ['fotos' => [UploadedFile::fake()->image('grande.jpg', 800, 800)->size(50)]])
        ->assertSessionHasErrors('fotos.0');

    expect($reporte->adjuntos()->count())->toBe(0);
});

test('no se pueden superar las fotos máximas por informe', function () {
    config(['adjuntos.max_por_entidad' => 2]);
    $investigador = investigador();
    $reporte = reporteDe($investigador, null, ['estado' => 'borrador']);
    fotoDe($reporte, $investigador);

    $this->actingAs($investigador)
        ->post(route('reportes.fotos.subir', $reporte), ['fotos' => [fotoPng('a.png'), fotoPng('b.png')]])
        ->assertSessionHasErrors('fotos');

    expect($reporte->adjuntos()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('si una foto del informe nuevo es inválida no se crea el informe ni queda ninguna foto en el disco', function () {
    $investigador = investigador();
    $programa = Programa::factory()->create();

    $this->actingAs($investigador)->post(route('reportes.store'), [
        'programa_id' => $programa->id,
        'titulo' => 'Informe que no debe quedar',
        'descripcion' => 'Descripción suficiente para el informe con foto dañada.',
        'fotos' => [fotoPng('buena.png'), UploadedFile::fake()->createWithContent('mala.png', "\x89PNG\r\n\x1a\ncorrupta")],
    ])->assertSessionHasErrors('fotos');

    expect(Reporte::where('titulo', 'Informe que no debe quedar')->exists())->toBeFalse()
        ->and(Adjunto::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

// ---------------------------------------------------------------------------
// Informes: quién puede subir, quitar y ver
// ---------------------------------------------------------------------------

test('no se añaden fotos a un informe ajeno ni a uno ya validado', function () {
    $autor = investigador();
    $borrador = reporteDe($autor, null, ['estado' => 'borrador']);
    $validado = reporteDe($autor, null, ['estado' => 'validado']);

    $this->actingAs(investigador())
        ->post(route('reportes.fotos.subir', $borrador), ['fotos' => [fotoPng()]])
        ->assertForbidden();

    $this->actingAs($autor)
        ->post(route('reportes.fotos.subir', $validado), ['fotos' => [fotoPng()]])
        ->assertForbidden();

    expect(Adjunto::count())->toBe(0);
});

test('el autor quita una foto: desaparece de la base y del disco', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador']);
    $foto = fotoDe($reporte, $autor);

    $this->actingAs($autor)->delete(route('reportes.fotos.eliminar', [$reporte, $foto]))->assertRedirect();

    expect(Adjunto::find($foto->id))->toBeNull()
        ->and(Storage::disk('local')->exists($foto->ruta))->toBeFalse();
});

test('una foto solo se sirve a través de su propio informe', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador']);
    $otro = reporteDe($autor, null, ['estado' => 'borrador']);
    $foto = fotoDe($reporte, $autor);

    $this->actingAs($autor)->get(route('reportes.fotos.ver', [$otro, $foto]))->assertNotFound();
    $this->actingAs($autor)->delete(route('reportes.fotos.eliminar', [$otro, $foto]))->assertNotFound();

    expect(Adjunto::find($foto->id))->not->toBeNull();
});

test('el autor ve su foto con cabeceras que impiden ejecutarla o cachearla', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador']);
    $foto = fotoDe($reporte, $autor);

    $respuesta = $this->actingAs($autor)->get(route('reportes.fotos.ver', [$reporte, $foto]));

    $respuesta->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');

    expect(Auditoria::where('accion', 'pgp.contenido_descifrado')->where('entidad_type', 'Adjunto')->where('entidad_id', $foto->id)->exists())->toBeTrue();
});

test('otro investigador no ve las fotos del informe', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'enviado']);
    $foto = fotoDe($reporte, $autor);

    $this->actingAs(investigador())->get(route('reportes.fotos.ver', [$reporte, $foto]))->assertForbidden();
});

test('el moderador asignado ve las fotos; uno de otro programa no', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'enviado']);
    $foto = fotoDe($reporte, $autor);

    $this->actingAs(moderadorDe($reporte))->get(route('reportes.fotos.ver', [$reporte, $foto]))->assertOk();
    $this->actingAs(moderadorDe(Programa::factory()->create()))->get(route('reportes.fotos.ver', [$reporte, $foto]))->assertForbidden();
});

test('la empresa ve las fotos cuando el informe ya se validó, pero no antes', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario);
    $autor = investigador();
    $enviado = reporteDe($autor, $programa, ['estado' => 'enviado']);
    $validado = reporteDe($autor, $programa, ['estado' => 'validado']);

    $this->actingAs($propietario)->get(route('reportes.fotos.ver', [$enviado, fotoDe($enviado, $autor)]))->assertForbidden();
    $this->actingAs($propietario)->get(route('reportes.fotos.ver', [$validado, fotoDe($validado, $autor)]))->assertOk();
});

test('el detalle del informe lista las fotos sin exponer ruta, disco ni clave', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador']);
    fotoDe($reporte, $autor);

    $fotos = $this->actingAs($autor)->get(route('reportes.show', $reporte))->assertOk()->inertiaProps()['fotos'];

    expect($fotos)->toHaveCount(1)
        ->and($fotos[0])->toHaveKeys(['id', 'nombre', 'sha256', 'url'])
        ->and($fotos[0])->not->toHaveKeys(['ruta', 'disco', 'clave_huella']);
});

test('si la foto guardada se altera, no se entrega y queda registrado', function () {
    $autor = investigador();
    $reporte = reporteDe($autor, null, ['estado' => 'borrador']);
    $foto = fotoDe($reporte, $autor);

    // Otra imagen, cifrada correctamente, en lugar de la original.
    Storage::disk('local')->put($foto->ruta, app(PgpService::class)->cifrarArchivo('otra imagen')['contenido']);

    $this->actingAs($autor)->get(route('reportes.fotos.ver', [$reporte, $foto]))->assertStatus(409);
    expect(Auditoria::where('accion', 'adjuntos.integridad_fallida')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Apelaciones
// ---------------------------------------------------------------------------

test('la apelación guarda sus fotos y sella su huella en la traza encadenada', function () {
    $sancionado = investigador();
    $sancion = sancionParaApelar($sancionado);

    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), [
        'motivo' => 'Adjunto capturas que prueban que el fallo era real.',
        'fotos' => [fotoPng('prueba.png')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $apelacion = Apelacion::where('sancion_id', $sancion->id)->firstOrFail();
    $foto = $apelacion->adjuntos()->firstOrFail();

    expect($apelacion->evidencia['fotos'][0]['sha256'])->toBe($foto->sha256);

    $presentada = ApelacionEvento::where('apelacion_id', $apelacion->id)->where('tipo', TrazaApelaciones::PRESENTADA)->firstOrFail();
    expect($presentada->datos['fotos_sha256'])->toBe([$foto->sha256])
        ->and(app(TrazaApelaciones::class)->verificar($apelacion))->toBeTrue();
});

test('ven la foto de la apelación quien la presentó y el administrador; nadie más', function () {
    $sancionado = investigador();
    $sancion = sancionParaApelar($sancionado);

    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), [
        'motivo' => 'Mi caso con evidencia.',
        'fotos' => [fotoPng()],
    ])->assertSessionHasNoErrors();

    $apelacion = Apelacion::where('sancion_id', $sancion->id)->firstOrFail();
    $foto = $apelacion->adjuntos()->firstOrFail();
    $url = route('apelaciones.fotos.ver', [$apelacion, $foto]);

    $this->actingAs($sancionado)->get($url)->assertOk();
    $this->actingAs(administrador())->get($url)->assertOk();
    $this->actingAs(investigador())->get($url)->assertForbidden();

    // Y aparece en el detalle de ambos.
    expect($this->actingAs($sancionado)->get(route('reputacion.apelacion', $apelacion))->inertiaProps()['apelacion']['fotos'])->toHaveCount(1);
    expect($this->actingAs(administrador())->get(route('apelaciones.show', $apelacion))->inertiaProps()['apelacion']['fotos'])->toHaveCount(1);
});

test('una apelación con una foto inválida no se presenta', function () {
    $sancionado = investigador();
    $sancion = sancionParaApelar($sancionado);

    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), [
        'motivo' => 'Con archivo falso.',
        'fotos' => [UploadedFile::fake()->createWithContent('falsa.jpg', 'no soy una foto')],
    ])->assertSessionHasErrors();

    expect(Apelacion::where('sancion_id', $sancion->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('apelar sin fotos sigue funcionando igual', function () {
    $sancionado = investigador();
    $sancion = sancionParaApelar($sancionado);

    $this->actingAs($sancionado)->post(route('reputacion.apelar', $sancion), ['motivo' => 'Sin fotos.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $apelacion = Apelacion::where('sancion_id', $sancion->id)->firstOrFail();
    expect($apelacion->evidencia)->toBeNull()
        ->and($apelacion->adjuntos()->count())->toBe(0);
});
