<?php

use App\Models\Programa;
use App\Models\Reporte;
use App\Services\Moderacion\AutoAsignadorModeradores;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('moderacion.limite_programas_por_moderador', 2);
    Config::set('moderacion.moderadores_por_programa', 1);
});

test('al pasar un programa a activo se asigna automaticamente al moderador con menor carga', function () {
    $moderadorA = investigador();
    $moderadorA->roles()->syncWithoutDetaching([rol('moderador')->id]);

    $moderadorB = investigador();
    $moderadorB->roles()->syncWithoutDetaching([rol('moderador')->id]);

    // Moderador B ya tiene 1 programa activo asignado
    $programaB = Programa::factory()->create(['estado' => 'activo']);
    $programaB->moderadores()->attach($moderadorB->id);

    // Nuevo programa se activa
    $nuevoPrograma = Programa::factory()->create(['estado' => 'activo']);

    $autoAsignador = app(AutoAsignadorModeradores::class);
    $asignados = $autoAsignador->asignarAPrograma($nuevoPrograma);

    expect($asignados)->toBe(1);
    expect($nuevoPrograma->fresh()->moderadores()->whereKey($moderadorA->id)->exists())->toBeTrue();
    expect($nuevoPrograma->fresh()->moderadores()->whereKey($moderadorB->id)->exists())->toBeFalse();
});

test('si todos los moderadores alcanzan el limite de capacidad se notifica al administrador', function () {
    $admin = administrador();

    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);

    // Asignar 2 programas activos para llenar la capacidad (limite configurado en 2)
    $prog1 = Programa::factory()->create(['estado' => 'activo']);
    $prog2 = Programa::factory()->create(['estado' => 'activo']);
    $prog1->moderadores()->attach($moderador->id);
    $prog2->moderadores()->attach($moderador->id);

    // Nuevo programa activo
    $prog3 = Programa::factory()->create(['estado' => 'activo']);

    $autoAsignador = app(AutoAsignadorModeradores::class);
    $asignados = $autoAsignador->asignarAPrograma($prog3);

    expect($asignados)->toBe(0);
    expect($prog3->fresh()->moderadores()->count())->toBe(0);

    // El admin debe haber recibido la notificación de capacidad alcanzada
    expect($admin->fresh()->notifications()->count())->toBeGreaterThan(0);
    $notificacion = $admin->fresh()->notifications()->first();
    expect($notificacion->data['titulo'])->toContain('Capacidad de moderación alcanzada');
});

test('cuando un moderador se libera se auto-asigna el programa pendiente y se notifica al admin', function () {
    $admin = administrador();

    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);

    $prog1 = Programa::factory()->create(['estado' => 'activo']);
    $prog2 = Programa::factory()->create(['estado' => 'activo']);
    $prog1->moderadores()->attach($moderador->id);
    $prog2->moderadores()->attach($moderador->id);

    // Prog3 queda sin moderadores porque el moderador estaba full
    $prog3 = Programa::factory()->create(['estado' => 'activo']);

    $autoAsignador = app(AutoAsignadorModeradores::class);
    expect($autoAsignador->asignarAPrograma($prog3))->toBe(0);

    // Liberamos a moderador pausando prog1
    $prog1->update(['estado' => 'en_pausa']);

    // Atendemos pendientes
    $asignados = $autoAsignador->atenderProgramasPendientes();

    expect($asignados)->toBe(1);
    expect($prog3->fresh()->moderadores()->whereKey($moderador->id)->exists())->toBeTrue();

    // Verificamos que se le notificó al admin de la asignación tras liberarse
    $titulos = $admin->fresh()->notifications()->get()->pluck('data.titulo');
    expect($titulos)->toContain('Programa auto-asignado');
});

test('no auto-asigna a un moderador con conflicto de interes en el programa', function () {
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);

    $programa = Programa::factory()->create(['estado' => 'activo']);

    // El moderador presentó un reporte previo como investigador en este programa
    Reporte::factory()->create([
        'programa_id' => $programa->id,
        'investigador_id' => $moderador->id,
    ]);

    $autoAsignador = app(AutoAsignadorModeradores::class);
    $asignados = $autoAsignador->asignarAPrograma($programa);

    expect($asignados)->toBe(0);
    expect($programa->fresh()->moderadores()->whereKey($moderador->id)->exists())->toBeFalse();
});

test('la asignacion manual por el administrador sigue funcionando y puede superar el limite', function () {
    $admin = administrador();
    $moderador = investigador();
    $moderador->roles()->syncWithoutDetaching([rol('moderador')->id]);

    // Asignamos 2 programas (llegando al límite)
    $prog1 = Programa::factory()->create(['estado' => 'activo']);
    $prog2 = Programa::factory()->create(['estado' => 'activo']);
    $prog1->moderadores()->attach($moderador->id);
    $prog2->moderadores()->attach($moderador->id);

    $prog3 = Programa::factory()->create(['estado' => 'activo']);

    // Admin fuerza la asignación manual del 3er programa
    $this->actingAs($admin);
    $response = $this->post(route('admin.programas.moderadores.asignar', [$prog3, $moderador]));
    $response->assertRedirect(route('admin.moderadores'));

    expect($prog3->fresh()->moderadores()->whereKey($moderador->id)->exists())->toBeTrue();
    expect($moderador->fresh()->programasModerados()->count())->toBe(3);
});
