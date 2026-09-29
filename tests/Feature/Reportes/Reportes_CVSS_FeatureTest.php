<?php

use App\Enums\Severidad;
use App\Models\Programa;
use App\Models\Reporte;
use App\Support\Cvss31;
use Illuminate\Testing\TestResponse;

function crearInforme(array $datos): TestResponse
{
    return test()->post(route('reportes.store'), [
        'programa_id' => Programa::factory()->create()->id,
        'titulo' => 'Test CVSS',
        'descripcion' => 'Test desc',
        ...$datos,
    ]);
}

test('cvss vector is saved correctly', function () {
    $user = investigador();
    $this->actingAs($user);

    crearInforme(['vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']);

    $this->assertDatabaseHas('reportes', [
        'investigador_id' => $user->id,
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
        'puntuacion_cvss' => 9.8,
        'severidad' => 'critica',
    ]);
});

test('la puntuacion y la severidad las calcula el servidor: las del navegador se ignoran', function () {
    $this->actingAs(investigador());

    // Un fallo de severidad baja (3.1) que intenta hacerse pasar por crítico para cobrar más puntos.
    crearInforme([
        'titulo' => 'Inflado',
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N',
        'puntuacion_cvss' => 10,
        'severidad' => 'critica',
    ])->assertSessionHasNoErrors();

    $reporte = Reporte::where('titulo', 'Inflado')->firstOrFail();
    expect((float) $reporte->puntuacion_cvss)->toBe(3.1)
        ->and($reporte->severidad)->toBe(Severidad::Baja);
});

test('sin vector no hay puntuacion ni severidad, aunque el navegador las mande', function () {
    $this->actingAs(investigador());

    crearInforme(['titulo' => 'Sin vector', 'puntuacion_cvss' => 9.9, 'severidad' => 'alta'])->assertSessionHasNoErrors();

    $reporte = Reporte::where('titulo', 'Sin vector')->firstOrFail();
    expect($reporte->puntuacion_cvss)->toBeNull()->and($reporte->severidad)->toBeNull();
});

test('un vector que no es CVSS 3.1 valido se rechaza', function (string $vector) {
    $this->actingAs(investigador());

    crearInforme(['vector_cvss' => $vector])->assertSessionHasErrors('vector_cvss');
})->with([
    'demasiado largo' => [str_repeat('A', 101)],
    'version 3.0' => ['CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
    'valor inventado' => ['CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
    'falta una metrica' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H'],
]);

test('al editar sin tocar el vector no se puede cambiar la severidad', function () {
    $investigador = investigador();
    $this->actingAs($investigador);
    $reporte = reporteDe($investigador, null, [
        'estado' => 'borrador',
        'vector_cvss' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N',
        'puntuacion_cvss' => 3.1,
        'severidad' => 'baja',
    ]);

    $this->put(route('reportes.update', $reporte), ['titulo' => 'Nuevo titulo', 'puntuacion_cvss' => 10, 'severidad' => 'critica'])
        ->assertSessionHasNoErrors();

    expect($reporte->fresh()->severidad)->toBe(Severidad::Baja)
        ->and((float) $reporte->fresh()->puntuacion_cvss)->toBe(3.1);
});

test('la categoria tiene que ser una de la lista', function () {
    $this->actingAs(investigador());

    crearInforme(['categoria' => 'categoria-inventada'])->assertSessionHasErrors('categoria');
    crearInforme(['titulo' => 'Con categoria', 'categoria' => 'idor'])->assertSessionHasNoErrors();
});

test('la calculadora del servidor coincide con la especificacion CVSS 3.1', function (string $vector, float $puntuacion, Severidad $severidad) {
    expect(Cvss31::calcular($vector))->toBe(['puntuacion' => $puntuacion, 'severidad' => $severidad]);
})->with([
    'critica' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8, Severidad::Critica],
    'alcance cambiado' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:H/I:L/A:N', 8.2, Severidad::Alta],
    'alta' => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:N', 8.1, Severidad::Alta],
    'media' => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N', 6.5, Severidad::Media],
    'baja' => ['CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:L/I:N/A:N', 3.1, Severidad::Baja],
    'sin impacto' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N', 0.0, Severidad::Ninguna],
    'maxima con alcance cambiado' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H', 10.0, Severidad::Critica],
    'privilegios altos y alcance cambiado' => ['CVSS:3.1/AV:L/AC:H/PR:H/UI:R/S:C/C:L/I:L/A:L', 4.7, Severidad::Media],
]);
