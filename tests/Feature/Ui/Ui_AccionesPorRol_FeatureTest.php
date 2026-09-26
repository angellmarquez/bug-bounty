<?php

use App\Models\Reporte;

// Separación de funciones: solo un investigador envía informes.
test('solo el investigador abre el formulario de informe y ve el boton de crear', function () {
    $programa = programaDeEmpresa(propietarioDeEmpresa(), ['estado' => 'activo', 'es_publico' => true]);

    $this->actingAs(investigador())->get(route('reportes.create'))->assertOk();
    $this->get(route('reportes.index'))->assertInertia(fn ($page) => $page->where('puedeCrear', true));

    foreach ([administrador(), moderadorDe($programa), propietarioDeEmpresa()] as $usuario) {
        $this->actingAs($usuario)->get(route('reportes.create'))->assertForbidden();
        $this->get(route('reportes.index'))->assertInertia(fn ($page) => $page->where('puedeCrear', false));
    }
});

test('el formulario de informe solo ofrece programas donde ABAC deja reportar', function () {
    $propietario = propietarioDeEmpresa();
    $abierto = programaDeEmpresa($propietario, ['estado' => 'activo', 'es_publico' => true]);
    programaDeEmpresa($propietario, ['estado' => 'activo', 'es_publico' => true, 'nivel_acceso' => 'alto']);

    $this->actingAs(investigador())->get(route('reportes.create'))
        ->assertInertia(fn ($page) => $page->where('programas', fn ($programas) => collect($programas)->pluck('id')->all() === [$abierto->id]));
});

test('el admin no ve en un programa el bloque de informes que enlaza a la cola de moderacion', function () {
    $programa = programaDeEmpresa(propietarioDeEmpresa(), ['estado' => 'activo', 'es_publico' => true]);

    $this->actingAs(administrador())->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeModerar', false)->where('conteosInformes', null));

    $this->actingAs(moderadorDe($programa))->get(route('programas.show', $programa))
        ->assertInertia(fn ($page) => $page->where('puedeModerar', true));
});

test('el resumen de la empresa solo cuenta los informes que puede ver', function () {
    $propietario = propietarioDeEmpresa();
    $programa = programaDeEmpresa($propietario);

    foreach (['borrador', 'enviado', 'rechazado', 'en_revision', 'validado'] as $estado) {
        Reporte::factory()->create(['programa_id' => $programa->id, 'estado' => $estado]);
    }

    $this->actingAs($propietario)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('roleStats.empresa.reportes_recibidos', 2)
            ->missing('roleStats.miembros'));

});
