<?php

use App\Mail\EmpresaInvitacionMail;
use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use Illuminate\Support\Facades\Mail;

test('company owner can create an invitation for a member', function () {
    Mail::fake();
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-invite@example.com');
    $this->actingAs($owner);

    $this->post(route('empresa.invitaciones.crear'), ['email' => 'new-member@example.com'])
        ->assertRedirect(route('empresa.dashboard'));

    expect(EmpresaInvitacion::where('empresa_id', $empresa->id)->where('email', 'new-member@example.com')->exists())->toBeTrue();
    Mail::assertSent(EmpresaInvitacionMail::class, fn ($mail) => $mail->hasTo('new-member@example.com'));
});

test('invited user can accept invitation with matching email', function () {
    $empresa = Empresa::factory()->aprobada()->create();
    $owner = usuarioEmpresa($empresa, 'owner-accept@example.com');
    $member = investigador(['email' => 'accept-member@example.com']);
    $invitacion = EmpresaInvitacion::create([
        'empresa_id' => $empresa->id,
        'email' => $member->email,
        'token' => str_repeat('a', 64),
        'rol_interno' => 'miembro',
        'estado' => 'pendiente',
        'invitado_por' => $owner->id,
        'expira_en' => now()->addDay(),
    ]);
    $this->actingAs($member);

    $this->get(route('empresa.invitacion', $invitacion->token))->assertOk();
    $this->post(route('empresa.invitacion.aceptar', $invitacion->token))->assertRedirect(route('empresa.dashboard'));

    expect($empresa->fresh()->usuarios()->whereKey($member->id)->exists())->toBeTrue()
        ->and($invitacion->fresh()->estado)->toBe('aceptada');
});
