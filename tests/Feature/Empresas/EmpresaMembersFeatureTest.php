<?php

use App\Models\Auditoria;
use App\Models\Empresa;

test('company owner can add and remove an active member', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-members@example.com');
    $member = investigador(['email' => 'member-members@example.com']);
    $this->actingAs($owner);

    $this->post(route('empresa.miembros.agregar'), ['email' => $member->email])
        ->assertRedirect(route('empresa.dashboard'));
    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeTrue();

    $this->delete(route('empresa.miembros.eliminar', $member))
        ->assertRedirect(route('empresa.dashboard'));
    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeFalse()
        ->and(Auditoria::where('entidad_type', 'empresa')->whereIn('accion', ['empresa.miembro.agregado', 'empresa.miembro.eliminado'])->count())->toBe(2);
});

test('company member cannot manage members', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-only@example.com');
    $member = investigador(['email' => 'member-only@example.com']);
    $empresa->usuarios()->attach($member, ['rol_interno' => 'miembro', 'estado' => 'activo']);
    $member->roles()->syncWithoutDetaching([rol('empresa')->id]);
    $this->actingAs($member);

    $this->post(route('empresa.miembros.agregar'), ['email' => $owner->email])->assertForbidden();
});
