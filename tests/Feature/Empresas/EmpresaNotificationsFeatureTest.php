<?php

use App\Mail\EmpresaEstadoMail;
use App\Models\Empresa;
use Illuminate\Support\Facades\Mail;

test('approving a company sends an email notification', function () {
    Mail::fake();
    $admin = administrador();
    $empresa = Empresa::factory()->create();
    $this->actingAs($admin);

    $this->post(route('admin.empresas.aprobar', $empresa))->assertRedirect();

    Mail::assertSent(EmpresaEstadoMail::class, fn ($mail) => $mail->hasTo($empresa->email));
});
