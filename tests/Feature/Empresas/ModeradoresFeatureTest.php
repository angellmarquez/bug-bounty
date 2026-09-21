<?php

use App\Models\Auditoria;

test('administrator can assign and revoke moderator role', function () {
    $admin = administrador();
    $usuario = investigador();
    $this->actingAs($admin);

    $this->post(route('admin.moderadores.asignar', $usuario))->assertRedirect(route('admin.moderadores'));
    expect($usuario->fresh()->roles()->where('slug', 'moderador')->exists())->toBeTrue();

    $this->delete(route('admin.moderadores.revocar', $usuario))->assertRedirect(route('admin.moderadores'));
    expect($usuario->fresh()->roles()->where('slug', 'moderador')->exists())->toBeFalse();
    expect(Auditoria::where('entidad_id', $usuario->id)->whereIn('accion', ['admin.moderador.asignado', 'admin.moderador.revocado'])->count())->toBe(2);
});

test('a moderator cannot assign moderators', function () {
    $this->actingAs(moderador());
    $usuario = investigador();

    $this->post(route('admin.moderadores.asignar', $usuario))->assertForbidden();
});

test('moderator management lists only eligible investigators', function () {
    $admin = administrador();
    $this->actingAs($admin);

    $response = $this->get(route('admin.moderadores'));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('usuariosDisponibles', fn ($usuarios) => collect($usuarios)->every(
                fn ($usuario) => in_array('investigador', $usuario['roles'], true)
                    && ! in_array('moderador', $usuario['roles'], true)
                    && ! in_array('administrador', $usuario['roles'], true)
            ))
        );
});
