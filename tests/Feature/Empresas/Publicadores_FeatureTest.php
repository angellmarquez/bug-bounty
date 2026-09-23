<?php

use App\Models\Empresa;
use App\Models\EmpresaInvitacion;
use App\Models\Programa;
use App\Models\User;
use App\Services\Empresas\MembresiaEmpresa;
use Illuminate\Support\Facades\Mail;

/** Los títulos de los avisos de un usuario. */
function titulosDe(User $usuario): array
{
    return $usuario->notifications()->get()->map(fn ($n) => $n->data['titulo'])->all();
}

/** Una empresa aprobada con su propietario. */
function empresaConPropietario(): array
{
    $dueno = propietarioDeEmpresa();

    return [$dueno, $dueno->empresas()->firstOrFail()];
}

function invitar(User $dueno, string $correo)
{
    return test()->actingAs($dueno)->post(route('empresa.invitaciones.crear'), ['email' => $correo]);
}

function invitacionPara(Empresa $empresa, User $dueno, User $invitado, array $atributos = []): EmpresaInvitacion
{
    return EmpresaInvitacion::create([
        'empresa_id' => $empresa->id,
        'usuario_id' => $invitado->id,
        'email' => $invitado->email,
        'token' => str()->random(64),
        'rol_interno' => 'publicador',
        'estado' => 'pendiente',
        'invitado_por' => $dueno->id,
        'expira_en' => now()->addDays(7),
        ...$atributos,
    ]);
}

// ---------------------------------------------------------------------------
// Invitar: solo investigadores registrados, por su correo
// ---------------------------------------------------------------------------

test('el propietario invita a un investigador registrado por su correo y le llega un aviso, sin correo electronico', function () {
    Mail::fake();
    [$dueno, $empresa] = empresaConPropietario();
    $invitado = investigador(['email' => 'Ana.Investigadora@Ejemplo.test']);

    invitar($dueno, 'ana.investigadora@ejemplo.test')->assertRedirect()->assertSessionHas('success');

    $invitacion = EmpresaInvitacion::where('empresa_id', $empresa->id)->firstOrFail();
    expect($invitacion->usuario_id)->toBe($invitado->id)
        ->and($invitacion->rol_interno)->toBe('publicador')
        ->and($invitacion->estado)->toBe('pendiente')
        ->and($invitacion->expira_en->isFuture())->toBeTrue()
        ->and(titulosDe($invitado))->toBe(['Te invitaron a formar parte de una empresa'])
        ->and($invitado->notifications()->first()->data['url'])->toBe('/invitaciones');
    Mail::assertNothingSent();
});

test('no se puede invitar a quien no esta registrado', function () {
    [$dueno, $empresa] = empresaConPropietario();

    invitar($dueno, 'nadie@ejemplo.test')->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'No hay ningún usuario registrado'));

    expect(EmpresaInvitacion::count())->toBe(0);
});

test('no se puede invitar a un moderador: conflicto de interes', function () {
    [$dueno] = empresaConPropietario();
    $moderador = moderador(['email' => 'mod@ejemplo.test']);

    invitar($dueno, $moderador->email)->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'Un moderador no puede formar parte de una empresa'));

    expect(EmpresaInvitacion::count())->toBe(0)->and(titulosDe($moderador))->toBe([]);
});

test('tampoco se invita a un administrador ni a quien no es investigador', function () {
    [$dueno] = empresaConPropietario();
    $admin = administrador(['email' => 'admin@ejemplo.test']);
    $otroDueno = propietarioDeEmpresa(['email' => 'otro-dueno@ejemplo.test']);

    invitar($dueno, $admin->email)->assertSessionHas('error');
    invitar($dueno, $otroDueno->email)->assertSessionHas('error');

    expect(EmpresaInvitacion::count())->toBe(0);
});

test('una sola empresa por usuario: no se invita a quien ya pertenece a otra ni a alguien de la propia', function () {
    [$dueno, $empresa] = empresaConPropietario();
    [, $otraEmpresa] = empresaConPropietario();
    $enOtra = publicadorDeEmpresa($otraEmpresa, ['email' => 'en-otra@ejemplo.test']);
    $enLaMia = publicadorDeEmpresa($empresa, ['email' => 'en-la-mia@ejemplo.test']);

    invitar($dueno, $enOtra->email)->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'solo se permite una por usuario'));
    invitar($dueno, $enLaMia->email)->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'ya forma parte de tu empresa'));

    expect(EmpresaInvitacion::count())->toBe(0);
});

test('no se duplica una invitacion pendiente ni se invita al propio propietario', function () {
    [$dueno] = empresaConPropietario();
    $invitado = investigador(['email' => 'dup@ejemplo.test']);

    invitar($dueno, $invitado->email)->assertSessionHas('success');
    invitar($dueno, $invitado->email)->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'invitación pendiente'));
    invitar($dueno, $dueno->email)->assertSessionHas('error');

    expect(EmpresaInvitacion::count())->toBe(1);
});

test('una empresa sin aprobar no puede invitar', function () {
    $empresa = Empresa::factory()->create();
    $dueno = conRol(User::factory()->create(), 'empresa');
    $empresa->usuarios()->attach($dueno, ['rol_interno' => 'propietario', 'estado' => 'activo']);
    $invitado = investigador(['email' => 'x@ejemplo.test']);

    // La empresa pendiente ni siquiera pasa el filtro de acceso operativo.
    $this->actingAs($dueno)->post(route('empresa.invitaciones.crear'), ['email' => $invitado->email])->assertForbidden();
    expect(EmpresaInvitacion::count())->toBe(0);
});

test('solo el propietario invita: un publicador o un investigador cualquiera reciben 403', function () {
    [, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    $objetivo = investigador(['email' => 'objetivo@ejemplo.test']);

    $this->actingAs($publicador)->post(route('empresa.invitaciones.crear'), ['email' => $objetivo->email])->assertForbidden();
    $this->actingAs(investigador())->post(route('empresa.invitaciones.crear'), ['email' => $objetivo->email])->assertForbidden();

    expect(EmpresaInvitacion::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Responder: aceptar, rechazar, caducar
// ---------------------------------------------------------------------------

test('al aceptar entra como publicador, conserva su rol de investigador, se avisa al propietario y se cierran sus otras invitaciones', function () {
    [$dueno, $empresa] = empresaConPropietario();
    [$otroDueno, $otraEmpresa] = empresaConPropietario();
    $invitado = investigador(['name' => 'Ana Investigadora']);
    $invitacion = invitacionPara($empresa, $dueno, $invitado);
    $otraInvitacion = invitacionPara($otraEmpresa, $otroDueno, $invitado);

    $this->actingAs($invitado)->post(route('invitaciones.aceptar', $invitacion))->assertRedirect(route('programas.gestion'));

    $miembro = $empresa->usuarios()->whereKey($invitado->id)->firstOrFail();
    expect($miembro->pivot->rol_interno)->toBe('publicador')
        ->and($miembro->pivot->estado)->toBe('activo')
        ->and($invitado->fresh()->roles()->pluck('slug')->all())->toBe(['investigador'])
        ->and($invitacion->fresh()->estado)->toBe('aceptada')
        ->and($otraInvitacion->fresh()->estado)->toBe('cancelada')
        ->and(titulosDe($dueno))->toBe(['Ana Investigadora aceptó tu invitación']);
});

test('rechazar deja la invitacion cerrada y avisa al propietario, sin unirse', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $invitado = investigador(['name' => 'Beto']);
    $invitacion = invitacionPara($empresa, $dueno, $invitado);

    $this->actingAs($invitado)->post(route('invitaciones.rechazar', $invitacion))->assertRedirect(route('invitaciones.index'));

    expect($invitacion->fresh()->estado)->toBe('rechazada')
        ->and($empresa->usuarios()->whereKey($invitado->id)->exists())->toBeFalse()
        ->and(titulosDe($dueno))->toBe(['Beto rechazó tu invitación']);
});

test('nadie responde la invitacion de otra persona', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $invitacion = invitacionPara($empresa, $dueno, investigador());

    $this->actingAs(investigador())->post(route('invitaciones.aceptar', $invitacion))->assertForbidden();
    $this->actingAs(investigador())->post(route('invitaciones.rechazar', $invitacion))->assertForbidden();

    expect($invitacion->fresh()->estado)->toBe('pendiente');
});

test('una invitacion caducada o ya respondida no se puede aceptar', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $invitado = investigador();
    $caducada = invitacionPara($empresa, $dueno, $invitado, ['expira_en' => now()->subDay()]);

    $this->actingAs($invitado)->post(route('invitaciones.aceptar', $caducada))->assertRedirect(route('invitaciones.index'))
        ->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'expiró'));

    $rechazada = invitacionPara($empresa, $dueno, $invitado, ['estado' => 'rechazada']);
    $this->actingAs($invitado)->post(route('invitaciones.aceptar', $rechazada))->assertSessionHas('error');

    expect($empresa->usuarios()->whereKey($invitado->id)->exists())->toBeFalse();
});

test('al aceptar se vuelven a comprobar las reglas: si mientras tanto es moderador o esta en otra empresa, no entra', function () {
    [$dueno, $empresa] = empresaConPropietario();
    [, $otraEmpresa] = empresaConPropietario();

    $seHizoModerador = investigador();
    $invitacionA = invitacionPara($empresa, $dueno, $seHizoModerador);
    $seHizoModerador->roles()->syncWithoutDetaching([rol('moderador')->id]);

    $seFueAOtra = investigador();
    $invitacionB = invitacionPara($empresa, $dueno, $seFueAOtra);
    $otraEmpresa->usuarios()->attach($seFueAOtra, ['rol_interno' => 'publicador', 'estado' => 'activo']);

    $this->actingAs($seHizoModerador)->post(route('invitaciones.aceptar', $invitacionA))
        ->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'Un moderador no puede'));
    $this->actingAs($seFueAOtra)->post(route('invitaciones.aceptar', $invitacionB))
        ->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'Ya perteneces a otra empresa'));

    expect($empresa->usuarios()->count())->toBe(1);   // solo el propietario
});

test('la pagina de invitaciones muestra solo las propias, con la empresa y quien invito', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $yo = investigador();
    invitacionPara($empresa, $dueno, $yo);
    invitacionPara($empresa, $dueno, investigador());

    $this->actingAs($yo)->get(route('invitaciones.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('invitaciones/Index')
        ->has('invitaciones', 1)
        ->where('invitaciones.0.estado', 'pendiente')
        ->where('invitaciones.0.vigente', true)
        ->where('invitaciones.0.invitada_por', $dueno->name));
});

test('el propietario cancela una invitacion pendiente y retira a un publicador, y ambos reciben un aviso', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $pendiente = investigador();
    $invitacion = invitacionPara($empresa, $dueno, $pendiente);
    $publicador = publicadorDeEmpresa($empresa);

    $this->actingAs($dueno)->delete(route('empresa.invitaciones.cancelar', $invitacion))->assertRedirect();
    $this->actingAs($dueno)->delete(route('empresa.miembros.eliminar', $publicador))->assertRedirect();

    expect($invitacion->fresh()->estado)->toBe('cancelada')
        ->and(titulosDe($pendiente))->toContain('Se canceló una invitación')
        ->and($empresa->usuarios()->whereKey($publicador->id)->exists())->toBeFalse()
        ->and(titulosDe($publicador))->toContain('Ya no formas parte de una empresa');
});

test('el propietario no se puede retirar y un publicador no retira a nadie', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    $otro = publicadorDeEmpresa($empresa);

    $this->actingAs($dueno)->delete(route('empresa.miembros.eliminar', $dueno))->assertSessionHas('error');
    $this->actingAs($publicador)->delete(route('empresa.miembros.eliminar', $otro))->assertForbidden();

    expect($empresa->usuarios()->count())->toBe(3);
});

test('un administrador no puede volver moderador a alguien que pertenece a una empresa', function () {
    [, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    rol('moderador');
    $this->actingAs(administrador());

    $this->post(route('admin.moderadores.asignar', $publicador))->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'forma parte de una empresa'));
    $this->put(route('admin.usuarios.update', $publicador), ['rol' => 'moderador'])->assertSessionHas('error');

    expect($publicador->fresh()->roles()->pluck('slug')->all())->toBe(['investigador']);
    $this->get(route('admin.moderadores'))->assertInertia(fn ($page) => $page->where('usuariosDisponibles', fn ($lista) => collect($lista)->pluck('id')->doesntContain($publicador->id)));
});

// ---------------------------------------------------------------------------
// Lo que puede y no puede hacer un publicador
// ---------------------------------------------------------------------------

test('el publicador crea, edita y publica programas de su empresa, pero no los borra', function () {
    [, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    $this->actingAs($publicador);

    $this->post(route('programas.store'), [
        'nombre' => 'Programa del publicador',
        'descripcion' => 'Descripción',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
    ])->assertRedirect();

    $programa = Programa::where('nombre', 'Programa del publicador')->firstOrFail();
    expect($programa->empresa_id)->toBe($empresa->id)->and($programa->creado_por)->toBe($publicador->id);

    $this->put(route('programas.update', $programa), [
        'nombre' => 'Renombrado', 'descripcion' => 'Nueva', 'objetivos' => [['tipo' => 'web', 'valor' => 'nuevo.ejemplo.test']],
    ])->assertRedirect();
    $this->post(route('programas.cambiar-estado', $programa), ['estado' => 'activo'])->assertRedirect();

    expect($programa->fresh()->nombre)->toBe('Renombrado')->and($programa->fresh()->estado->value)->toBe('activo');
    $this->delete(route('programas.destroy', $programa))->assertForbidden();
});

test('el publicador no toca los programas de otra empresa', function () {
    [, $empresa] = empresaConPropietario();
    [$otroDueno] = empresaConPropietario();
    $ajeno = programaDeEmpresa($otroDueno);
    $this->actingAs(publicadorDeEmpresa($empresa));

    $this->put(route('programas.update', $ajeno), ['nombre' => 'Hackeado', 'descripcion' => 'x'])->assertForbidden();
    $this->post(route('programas.cambiar-estado', $ajeno), ['estado' => 'en_pausa'])->assertForbidden();
    $this->get(route('programas.edit', $ajeno))->assertForbidden();
});

test('si la empresa deja de estar aprobada el publicador ya no puede publicar', function () {
    [, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    $empresa->update(['estado' => 'suspendida']);

    $this->actingAs($publicador)->post(route('programas.store'), [
        'nombre' => 'No debería', 'descripcion' => 'x', 'objetivos' => [['tipo' => 'web', 'valor' => 'a.test']],
    ])->assertForbidden();

    expect(Programa::where('nombre', 'No debería')->exists())->toBeFalse();
});

test('el publicador ve en su gestion los programas de su empresa (sin conteo de informes) y en programas los borradores de su empresa', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $propio = programaDeEmpresa($dueno, ['estado' => 'borrador']);
    reporteDe(investigador(), $propio, ['estado' => 'enviado']);
    [$otroDueno] = empresaConPropietario();
    $ajeno = programaDeEmpresa($otroDueno, ['estado' => 'borrador']);
    $publicador = publicadorDeEmpresa($empresa);

    $this->actingAs($publicador)->get(route('programas.gestion'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('programas.data', fn ($lista) => collect($lista)->pluck('id')->all() === [$propio->id])
        ->where('programas.data.0.reportes_count', null)
        ->missing('programas.data.0.reportes'));

    $ids = collect($this->get(route('programas.index'))->inertiaProps()['programas']['data'])->pluck('id');
    expect($ids)->toContain($propio->id)->not->toContain($ajeno->id);
});

// ---------------------------------------------------------------------------
// Los informes de la empresa son solo del propietario
// ---------------------------------------------------------------------------

test('el publicador no ve los informes que recibe la empresa, el propietario si', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $programa = programaDeEmpresa($dueno);
    $reporte = reporteDe(investigador(), $programa, ['estado' => 'enviado']);
    $publicador = publicadorDeEmpresa($empresa);

    $this->actingAs($publicador)->get(route('reportes.show', $reporte))->assertForbidden();
    $this->actingAs($publicador)->get(route('empresa.reportes'))->assertForbidden();
    $this->actingAs($publicador)->getJson(route('reportes.vista-rapida', $reporte))->assertForbidden();
    $ids = collect($this->actingAs($publicador)->get(route('reportes.index'))->inertiaProps()['reportes']['data'])->pluck('id');
    expect($ids)->not->toContain($reporte->id);

    $this->actingAs($dueno)->get(route('reportes.show', $reporte))->assertOk();
    $this->actingAs($dueno)->get(route('empresa.reportes'))->assertOk();
});

test('el panel de empresa de un publicador lleva a sus programas, sin informes', function () {
    [, $empresa] = empresaConPropietario();

    $this->actingAs(publicadorDeEmpresa($empresa))->get(route('empresa.dashboard'))->assertRedirect(route('programas.gestion'));
});

test('al avisar de informes nuevos solo se avisa al propietario, no a los publicadores', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $programa = programaDeEmpresa($dueno);
    $publicador = publicadorDeEmpresa($empresa);
    $autor = investigador();
    $reporte = reporteDe($autor, $programa, [
        'estado' => 'borrador',
        'poc' => pocCifrado(['evidencia' => 'Evidencia de prueba.']),
    ]);

    $this->actingAs($autor)->post(route('reportes.enviar', $reporte))->assertRedirect();

    expect(titulosDe($dueno))->toContain('Nuevo informe recibido')
        ->and(titulosDe($publicador))->toBe([]);
});

// ---------------------------------------------------------------------------
// Conflicto de interés: quien es miembro no reporta a su empresa
// ---------------------------------------------------------------------------

test('un miembro no puede reportar a los programas de su empresa pero si a los demas', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $propio = programaDeEmpresa($dueno);
    [$otroDueno] = empresaConPropietario();
    $ajeno = programaDeEmpresa($otroDueno);
    $publicador = publicadorDeEmpresa($empresa);
    $this->actingAs($publicador);

    $this->post(route('reportes.store'), ['programa_id' => $propio->id, 'titulo' => 'A mi empresa', 'descripcion' => 'x'])->assertForbidden();
    $this->post(route('reportes.store'), ['programa_id' => $ajeno->id, 'titulo' => 'A otra', 'descripcion' => 'x'])->assertRedirect();

    $ids = collect($this->get(route('reportes.create'))->inertiaProps()['programas'])->pluck('id');
    expect($ids)->toContain($ajeno->id)->not->toContain($propio->id)
        ->and(Programa::find($propio->id)->reportes()->count())->toBe(0);
});

test('quien ya habia reportado a la empresa puede unirse, sigue viendo sus informes y no puede enviar nuevos ni el borrador que tenia', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $programa = programaDeEmpresa($dueno);
    $investigador = investigador();
    $enviado = reporteDe($investigador, $programa, ['estado' => 'enviado', 'enviado_en' => now()->subDays(2)]);
    $borrador = reporteDe($investigador, $programa, [
        'estado' => 'borrador',
        'enviado_en' => null,
        'poc' => pocCifrado(['evidencia' => 'Evidencia de prueba.']),
    ]);
    $invitacion = invitacionPara($empresa, $dueno, $investigador);

    $this->actingAs($investigador)->post(route('invitaciones.aceptar', $invitacion))->assertRedirect();

    // Sigue viendo el estado de lo que ya presentó...
    $this->actingAs($investigador)->get(route('reportes.show', $enviado))->assertOk();
    $ids = collect($this->get(route('reportes.index'))->inertiaProps()['reportes']['data'])->pluck('id');
    expect($ids)->toContain($enviado->id);

    // ...pero no puede reportar más a esa empresa mientras sea miembro.
    $this->post(route('reportes.enviar', $borrador))->assertForbidden();
    $this->get(route('reportes.edit', $borrador))->assertForbidden();
    $this->post(route('reportes.store'), ['programa_id' => $programa->id, 'titulo' => 'Otro', 'descripcion' => 'x'])->assertForbidden();
    expect($borrador->fresh()->estado->value)->toBe('borrador');

    // Al dejar de ser miembro vuelve a poder reportar.
    $this->actingAs($dueno)->delete(route('empresa.miembros.eliminar', $investigador))->assertRedirect();
    $this->actingAs($investigador)->post(route('reportes.enviar', $borrador))->assertRedirect();
    expect($borrador->fresh()->estado->value)->toBe('enviado');
});

test('la pagina del programa avisa a los miembros de que no pueden reportarle', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $programa = programaDeEmpresa($dueno);

    $this->actingAs(publicadorDeEmpresa($empresa))->get(route('programas.show', $programa))->assertOk()->assertInertia(fn ($page) => $page
        ->where('puedeReportar', false)
        ->where('esDeMiEmpresa', true));

    $this->actingAs(investigador())->get(route('programas.show', $programa))->assertInertia(fn ($page) => $page
        ->where('puedeReportar', true)
        ->where('esDeMiEmpresa', false));
});

test('el estado de cuenta indica el papel en la empresa y las invitaciones pendientes', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa);
    $invitado = investigador();
    invitacionPara($empresa, $dueno, $invitado);

    $cuenta = $this->actingAs($publicador)->get(route('programas.gestion'))->viewData('page')['props']['cuenta'];
    expect($cuenta['empresa']['rol_interno'])->toBe('publicador')->and($cuenta['invitaciones_pendientes'])->toBe(0);

    $cuentaInvitado = $this->actingAs($invitado)->get(route('dashboard'))->viewData('page')['props']['cuenta'];
    expect($cuentaInvitado['empresa'])->toBeNull()->and($cuentaInvitado['invitaciones_pendientes'])->toBe(1);
});

test('la regla de rechazo es la unica fuente: coincide con lo que responde el servicio', function () {
    [, $empresa] = empresaConPropietario();
    $servicio = app(MembresiaEmpresa::class);

    expect($servicio->motivoDeRechazo(investigador(), $empresa))->toBeNull()
        ->and($servicio->motivoDeRechazo(moderador(), $empresa))->toContain('conflicto de interés')
        ->and($servicio->motivoDeRechazo(publicadorDeEmpresa($empresa), $empresa))->toContain('ya forma parte');
});

test('el panel del propietario lista a sus publicadores con su rol y desde cuando forman parte', function () {
    [$dueno, $empresa] = empresaConPropietario();
    $publicador = publicadorDeEmpresa($empresa, ['name' => 'Paula Publicadora']);

    $this->actingAs($dueno)->get(route('empresa.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('empresa.puedeGestionarMiembros', true)
        ->where('empresa.usuarios', fn ($lista) => collect($lista)->contains(fn ($u) => $u['id'] === $publicador->id
            && $u['rol_interno'] === 'publicador'
            && $u['desde'] !== null)));
});
