<?php

use App\Models\Programa;
use Illuminate\Testing\TestResponse;

/** @param  array<string, mixed>  $datos */
function crearPrograma(array $datos): TestResponse
{
    return test()->actingAs(propietarioDeEmpresa())->post(route('programas.store'), [
        'nombre' => 'Programa de prueba',
        'descripcion' => 'Programa de prueba para validar nombre y objetivos',
        'objetivos' => [['tipo' => 'web', 'valor' => 'https://ejemplo.com']],
        ...$datos,
    ]);
}

test('el nombre del programa necesita al menos 3 caracteres', function () {
    crearPrograma(['nombre' => 'ab'])->assertSessionHasErrors('nombre');
    crearPrograma(['nombre' => 'abc'])->assertSessionHasNoErrors();
});

test('un objetivo web o API acepta URLs, dominios con comodin e IPs', function (string $tipo, string $valor) {
    crearPrograma(['objetivos' => [['tipo' => $tipo, 'valor' => $valor]]])->assertSessionHasNoErrors();
})->with([
    ['web', 'https://app.ejemplo.com'],
    ['web', 'https://app.ejemplo.com:8443/login?next=/'],
    ['web', '*.ejemplo.com'],
    ['web', 'ejemplo.com'],
    ['api', 'https://api.ejemplo.com/v1'],
    ['api', '10.0.0.5:8080/api'],
]);

test('un objetivo web o API rechaza lo que no es una direccion', function (string $tipo, string $valor) {
    crearPrograma(['objetivos' => [['tipo' => $tipo, 'valor' => $valor]]])->assertSessionHasErrors('objetivos.0.valor');

    expect(Programa::query()->where('nombre', 'Programa de prueba')->exists())->toBeFalse();
})->with([
    ['web', 'mi pagina web'],
    ['web', 'javascript:alert(1)'],
    ['web', 'ftp://ejemplo.com'],
    ['api', 'la api de pagos'],
]);

test('los objetivos movil y otro son texto libre', function (string $tipo) {
    crearPrograma(['objetivos' => [['tipo' => $tipo, 'valor' => 'App Banca (com.ejemplo.banca)']]])->assertSessionHasNoErrors();
})->with(['movil', 'otro']);

test('al editar tambien se validan nombre y objetivos', function () {
    $duena = propietarioDeEmpresa();
    $programa = programaDeEmpresa($duena);

    $this->actingAs($duena)->put(route('programas.update', $programa), ['nombre' => 'x'])
        ->assertSessionHasErrors('nombre');
    $this->actingAs($duena)->put(route('programas.update', $programa), ['objetivos' => [['tipo' => 'web', 'valor' => 'no es una url']]])
        ->assertSessionHasErrors('objetivos.0.valor');
});
