<?php

use App\Models\Auditoria;

test('administrator can assign and revoke moderator role', function () {
    $admin = administrador();
    $usuario = investigador();
    $this->actingAs($admin);

    $this->post(route('admin.moderadores.asignar', $usuario))->assertRedirect(route('admin.moderadores'));
    expect($usuario->fresh()->roles()->where('slug', 'moderador')->exists())->toBeTrue();
    // SoD: Un moderador no retiene el rol de investigador simultáneamente
    expect($usuario->fresh()->roles()->where('slug', 'investigador')->exists())->toBeFalse();

    $this->delete(route('admin.moderadores.revocar', $usuario))->assertRedirect(route('admin.moderadores'));
    expect($usuario->fresh()->roles()->where('slug', 'moderador')->exists())->toBeFalse();
    // Al revocar el cargo, regresa de forma segura a investigador
    expect($usuario->fresh()->roles()->where('slug', 'investigador')->exists())->toBeTrue();
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

test('changing role from moderator detaches assigned programs', function () {
    $admin = administrador();
    $mod = moderador();
    $programa = \App\Models\Programa::factory()->create();
    $mod->programasModerados()->attach($programa->id);
    expect($mod->programasModerados()->count())->toBe(1);

    $this->actingAs($admin);
    rol('investigador');

    $this->put(route('admin.usuarios.update', $mod), ['rol' => 'investigador'])
        ->assertRedirect(route('admin.usuarios'));

    expect($mod->fresh()->roles()->where('slug', 'investigador')->exists())->toBeTrue();
    expect($mod->fresh()->programasModerados()->count())->toBe(0);
});

