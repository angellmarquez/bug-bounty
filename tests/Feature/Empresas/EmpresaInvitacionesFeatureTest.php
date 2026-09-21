<?php

use App\Models\Empresa;
use App\Models\EmpresaInvitacion;

test('company owner can create an invitation for a registered investigator', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-invite@example.com');
    $invitado = investigador(['email' => 'new-member@example.com']);
    $this->actingAs($owner);

    $this->post(route('empresa.invitaciones.crear'), ['email' => 'new-member@example.com'])
        ->assertRedirect(route('empresa.dashboard'));

    $invitacion = EmpresaInvitacion::where('empresa_id', $empresa->id)->firstOrFail();
    expect($invitacion->usuario_id)->toBe($invitado->id)->and($invitacion->rol_interno)->toBe('publicador');
});

test('invited user can accept the invitation from the platform', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-accept@example.com');
    $member = investigador(['email' => 'accept-member@example.com']);
    $invitacion = EmpresaInvitacion::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => $member->id,
        'email' => $member->email,
        'token' => str_repeat('a', 64),
        'rol_interno' => 'publicador',
        'estado' => 'pendiente',
        'invitado_por' => $owner->id,
        'expira_en' => now()->addDay(),
    ]);
    $this->actingAs($member);

    $this->get(route('invitaciones.index'))->assertOk();
    $this->post(route('invitaciones.aceptar', $invitacion))->assertRedirect(route('programas.gestion'));

    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeTrue()
        ->and($invitacion->fresh()->estado)->toBe('aceptada');
});
