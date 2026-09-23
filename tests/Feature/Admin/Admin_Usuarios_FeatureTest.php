<?php

use App\Models\Sancion;
use App\Models\User;

/**
 * Busca la ficha de un usuario dentro de la página del panel.
 *
 * @param  array<int, array<string, mixed>>  $fichas
 * @return array<string, mixed>|null
 */
function fichaDe(array $fichas, User $usuario): ?array
{
    return collect($fichas)->firstWhere('id', $usuario->id);
}

test('el panel muestra el tipo de cada cuenta, la empresa y el rango solo de los investigadores', function () {
    $admin = administrador();
    $moderador = moderador();
    $propietario = propietarioDeEmpresa();
    $investigador = investigador();
    $investigador->forceFill(['reputation_score' => 120])->save();

    $this->actingAs($admin)->get(route('admin.usuarios'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/usuarios/Index')
            ->where('usuarios.data', function ($fichas) use ($admin, $moderador, $propietario, $investigador) {
                $fichas = collect($fichas)->all();

                expect(fichaDe($fichas, $admin))->toMatchArray(['tipo' => 'administrador', 'reputation_score' => null, 'empresa' => null])
                    ->and(fichaDe($fichas, $moderador))->toMatchArray(['tipo' => 'moderador', 'reputation_score' => null])
                    ->and(fichaDe($fichas, $propietario))->toMatchArray(['tipo' => 'empresa', 'reputation_score' => null])
                    ->and(fichaDe($fichas, $propietario)['empresa'])->toBe($propietario->empresaActiva()->nombre_comercial ?? $propietario->empresaActiva()->razon_social)
                    ->and(fichaDe($fichas, $investigador))->toMatchArray(['tipo' => 'investigador', 'reputation_score' => 120, 'empresa' => null]);

                return true;
            }));
});

test('el panel distingue cuentas activas, suspendidas y desactivadas', function () {
    $admin = administrador();
    $activo = investigador();
    $desactivado = investigador(['is_active' => false]);
    $suspendido = investigador();
    Sancion::factory()->create([
        'usuario_id' => $suspendido->id,
        'suspension_desde' => now()->subDay(),
        'suspension_hasta' => now()->addDays(6),
    ]);
    // Una suspensión que ya terminó no cuenta.
    Sancion::factory()->create([
        'usuario_id' => $activo->id,
        'suspension_desde' => now()->subDays(10),
        'suspension_hasta' => now()->subDays(3),
    ]);

    $this->actingAs($admin)->get(route('admin.usuarios'))
        ->assertInertia(fn ($page) => $page->where('usuarios.data', function ($fichas) use ($activo, $desactivado, $suspendido) {
            $fichas = collect($fichas)->all();

            expect(fichaDe($fichas, $activo))->toMatchArray(['estado' => 'activa', 'suspendido_hasta' => null])
                ->and(fichaDe($fichas, $desactivado)['estado'])->toBe('desactivada')
                ->and(fichaDe($fichas, $suspendido)['estado'])->toBe('suspendida')
                ->and(fichaDe($fichas, $suspendido)['suspendido_hasta'])->not->toBeNull();

            return true;
        }));
});

test('el panel filtra por tipo de cuenta y por estado', function () {
    $admin = administrador();
    $moderador = moderador();
    $investigador = investigador();
    $suspendido = investigador();
    Sancion::factory()->create([
        'usuario_id' => $suspendido->id,
        'suspension_desde' => now()->subDay(),
        'suspension_hasta' => now()->addDay(),
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.usuarios', ['tipo' => 'moderador']))
        ->assertInertia(fn ($page) => $page
            ->where('usuarios.data', fn ($fichas) => collect($fichas)->pluck('id')->all() === [$moderador->id]));

    $this->get(route('admin.usuarios', ['tipo' => 'investigador', 'estado' => 'suspendida']))
        ->assertInertia(fn ($page) => $page
            ->where('usuarios.data', fn ($fichas) => collect($fichas)->pluck('id')->all() === [$suspendido->id]));

    $this->get(route('admin.usuarios', ['tipo' => 'investigador', 'estado' => 'activa']))
        ->assertInertia(fn ($page) => $page
            ->where('usuarios.data', fn ($fichas) => collect($fichas)->pluck('id')->all() === [$investigador->id]));
});

test('un filtro desconocido se rechaza en vez de ignorarse', function () {
    $this->actingAs(administrador())
        ->get(route('admin.usuarios', ['tipo' => 'superusuario']))
        ->assertSessionHasErrors('tipo');
});

test('desde el panel de usuarios ya no se puede cambiar el rol de nadie', function () {
    $admin = administrador();
    $investigador = investigador();

    $this->actingAs($admin)
        ->put("/admin/usuarios/{$investigador->id}", ['rol' => 'administrador'])
        ->assertNotFound();

    expect($investigador->fresh()->roles()->pluck('slug')->all())->toBe(['investigador']);
});

test('solo el administrador ve el panel de usuarios', function (Closure $usuario) {
    $this->actingAs($usuario())->get(route('admin.usuarios'))->assertForbidden();
})->with([
    'investigador' => [fn () => investigador()],
    'moderador' => [fn () => moderador()],
    'empresa' => [fn () => propietarioDeEmpresa()],
]);
