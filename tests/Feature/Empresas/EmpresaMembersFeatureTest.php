<?php

use App\Models\Auditoria;
use App\Models\Empresa;

test('company owner can remove an active member and it is audited', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-members@example.com');
    $member = publicadorDeEmpresa($empresa);
    $this->actingAs($owner);

    $this->delete(route('empresa.miembros.eliminar', $member))
        ->assertRedirect(route('empresa.dashboard'));

    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeFalse()
        ->and(Auditoria::where('entidad_type', 'Empresa')->where('accion', 'empresa.miembro.eliminado')->count())->toBe(1);
});

test('nobody can be added to a company without accepting an invitation', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-only@example.com');
    $member = investigador(['email' => 'member-only@example.com']);
    $this->actingAs($owner);

    // La ruta antigua que añadía a cualquier usuario sin su consentimiento ya no existe.
    $this->post('/empresa/miembros', ['email' => $member->email])->assertNotFound();

    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeFalse();
});

test('a publisher cannot manage members', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-block@example.com');
    $publicador = publicadorDeEmpresa($empresa);
    $this->actingAs($publicador);

    $this->delete(route('empresa.miembros.eliminar', $owner))->assertForbidden();
});
