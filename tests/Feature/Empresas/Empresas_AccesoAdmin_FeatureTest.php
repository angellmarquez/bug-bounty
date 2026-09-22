<?php

use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
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
            ->where('empresa.esAdmin', true)
            ->where('empresa.puedeGestionarMiembros', true));
});

test('un administrador que además tiene el rol empresa no queda bloqueado por su falta de empresa', function () {
    $admin = conRol(User::factory()->create(), ['administrador', 'empresa']);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->get(route('empresa.reportes', ['empresa' => Empresa::factory()->aprobada()->create()->id]))->assertOk();
});

test('el administrador invita a un investigador y la invitacion queda visible en el panel', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $invitado = investigador(['email' => 'nuevo@example.test']);
    $this->actingAs(administrador());

    $this->post(route('empresa.invitaciones.crear'), ['empresa_id' => $empresa->id, 'email' => 'nuevo@example.test'])
        ->assertRedirect(route('empresa.dashboard', ['empresa' => $empresa->id]))
        ->assertSessionHas('success');

    $this->get(route('empresa.dashboard', ['empresa' => $empresa->id]))
        ->assertInertia(fn ($page) => $page
            ->where('empresa.invitaciones.0.email', 'nuevo@example.test')
            ->where('empresa.invitaciones.0.nombre', $invitado->name));
});

test('el administrador retira publicadores de una empresa', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $existente = publicadorDeEmpresa($empresa);
    $this->actingAs(administrador());

    $this->delete(route('empresa.miembros.eliminar', $existente).'?empresa_id='.$empresa->id)->assertRedirect();
    expect($empresa->usuarios()->whereKey($existente->id)->exists())->toBeFalse();
});

test('sin indicar la empresa el administrador no puede gestionar miembros', function () {
    $this->actingAs(administrador())
        ->post(route('empresa.invitaciones.crear'), ['email' => 'nuevo@example.test'])
        ->assertStatus(422);
});

test('un publicador no ve las invitaciones que hizo el propietario', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $dueno = propietarioDe($empresa);
    EmpresaInvitacion::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => investigador()->id,
        'email' => 'pendiente@example.test',
        'token' => 'token-secreto',
        'rol_interno' => 'publicador',
        'estado' => 'pendiente',
        'invitado_por' => $dueno->id,
        'expira_en' => now()->addDays(7),
    ]);

    // Su panel de empresa lo lleva a sus programas: no ve invitaciones ni informes.
    $this->actingAs(publicadorDeEmpresa($empresa))
        ->get(route('empresa.dashboard'))
        ->assertRedirect(route('programas.gestion'));
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

test('un administrador no puede quitarse su propio rol de administrador', function () {
    $admin = administrador();
    administrador();
    rol('empresa');

    $this->actingAs($admin)
        ->put(route('admin.usuarios.update', $admin), ['rol' => 'empresa'])
        ->assertRedirect(route('admin.usuarios'))
        ->assertSessionHas('error');

    expect($admin->fresh()->roles()->pluck('slug')->all())->toBe(['administrador']);
});

test('no se puede quitar el rol al último administrador', function () {
    $ultimo = administrador();
    $otro = administrador();
    rol('investigador');
    $this->actingAs($otro);

    // Con dos administradores se puede degradar a uno...
    $this->put(route('admin.usuarios.update', $ultimo), ['rol' => 'investigador'])->assertSessionHasNoErrors();
    expect($ultimo->fresh()->roles()->pluck('slug')->all())->toBe(['investigador']);

    // ...pero el que queda es el último y no puede ser degradado por nadie.
    $tercero = administrador();
    $this->actingAs($tercero);
    $this->put(route('admin.usuarios.update', $otro), ['rol' => 'investigador'])->assertSessionHasNoErrors();
    $this->put(route('admin.usuarios.update', $tercero), ['rol' => 'investigador'])->assertSessionHas('error');
    expect($tercero->fresh()->roles()->pluck('slug')->all())->toBe(['administrador']);
});
