<?php

use App\Models\Empresa;
use App\Models\Programa;
use App\Models\User;

function propietarioDe(Empresa $empresa): User
{
    $usuario = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($usuario, ['rol_interno' => 'propietario', 'estado' => 'activo']);

    return $usuario;
}

test('el administrador sin empresa que abre /empresa es enviado a elegir una', function () {
    $this->actingAs(administrador())
        ->get(route('empresa.dashboard'))
        ->assertRedirect(route('admin.empresas'));
});

test('el administrador abre el panel de cualquier empresa con permisos completos', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    propietarioDe($empresa);

    $this->actingAs(administrador())
        ->get(route('empresa.dashboard', ['empresa' => $empresa->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresa/Dashboard')
            ->where('empresa.id', $empresa->id)
            ->where('empresa.esAdmin', true));
});

test('un administrador que además tiene el rol empresa no queda bloqueado por su falta de empresa', function () {
    $admin = conRol(User::factory()->create(), ['administrador', 'empresa']);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->get(route('empresa.reportes', ['empresa' => Empresa::factory()->aprobada()->create()->id]))->assertOk();
});

test('el administrador no puede crear un programa, ni siquiera a nombre de una empresa aprobada', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $this->actingAs(administrador());

    $this->post(route('programas.store'), [
        'nombre' => 'Programa del admin',
        'descripcion' => 'Creado por el administrador.',
        'empresa_id' => $empresa->id,
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.acme.test']],
    ])->assertForbidden();

    expect(Programa::where('nombre', 'Programa del admin')->exists())->toBeFalse();

    // Crear el formulario también queda vedado, no solo el envío.
    $this->get(route('programas.create'))->assertForbidden();
});

test('una empresa no puede asignar su programa a otra empresa enviando empresa_id', function () {
    $otra = Empresa::factory()->aprobada()->create();
    $propietario = propietarioDeEmpresa();
    $this->actingAs($propietario);

    $this->post(route('programas.store'), [
        'nombre' => 'Programa de mi empresa',
        'descripcion' => 'x',
        'empresa_id' => $otra->id,
        'objetivos' => [['tipo' => 'web', 'valor' => 'x.test']],
    ]);

    expect(Programa::where('nombre', 'Programa de mi empresa')->value('empresa_id'))
        ->toBe($propietario->empresas()->firstOrFail()->id)
        ->not->toBe($otra->id);
});
