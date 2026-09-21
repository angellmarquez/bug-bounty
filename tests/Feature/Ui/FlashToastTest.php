<?php

test('los mensajes success de la sesión llegan a la interfaz como toast', function () {
    $this->actingAs(investigador());

    $pagina = $this->withSession(['success' => 'Reporte enviado exitosamente.'])
        ->get(route('dashboard'))
        ->viewData('page');

    expect($pagina['flash']['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Reporte enviado exitosamente.']);
});

test('los mensajes error de la sesión llegan a la interfaz como toast de error', function () {
    $this->actingAs(investigador());

    $pagina = $this->withSession(['error' => 'No se pudo completar la acción.'])
        ->get(route('dashboard'))
        ->viewData('page');

    expect($pagina['flash']['toast'] ?? null)->toBe(['type' => 'error', 'message' => 'No se pudo completar la acción.']);
});

test('sin mensaje en la sesión no se envía ningún toast', function () {
    $this->actingAs(investigador());

    $pagina = $this->get(route('dashboard'))->viewData('page');

    expect($pagina['flash']['toast'] ?? null)->toBeNull();
});
