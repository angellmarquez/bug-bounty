<?php

use App\Models\Programa;

test('la empresa guarda un poc_schema valido al crear el programa', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa con PoC',
        'descripcion' => 'Descripcion',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
        'poc_schema' => [
            ['name' => 'url', 'label' => 'URL afectada', 'type' => 'url', 'required' => true],
            [
                'name' => 'impacto',
                'label' => 'Impacto',
                'type' => 'select',
                'required' => true,
                'options' => [['value' => 'bajo', 'label' => 'Bajo'], ['value' => 'alto', 'label' => 'Alto']],
            ],
        ],
    ]);

    $response->assertRedirect();
    $programa = Programa::where('nombre', 'Programa con PoC')->firstOrFail();
    expect($programa->poc_schema)->toHaveCount(2)
        ->and($programa->poc_schema[0]['name'])->toBe('url')
        ->and($programa->poc_schema[1]['options'])->toHaveCount(2);
});

test('rechaza un tipo de campo de poc_schema que no existe', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa invalido',
        'descripcion' => 'Descripcion',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
        'poc_schema' => [
            ['name' => 'raro', 'label' => 'Raro', 'type' => 'archivo_binario'],
        ],
    ]);

    $response->assertSessionHasErrors('poc_schema.0.type');
    $this->assertDatabaseMissing('programas', ['nombre' => 'Programa invalido']);
});

test('rechaza un campo de poc_schema sin etiqueta', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);

    $response = $this->post(route('programas.store'), [
        'nombre' => 'Programa invalido 2',
        'descripcion' => 'Descripcion',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
        'poc_schema' => [
            ['name' => 'sin_label', 'type' => 'text'],
        ],
    ]);

    $response->assertSessionHasErrors('poc_schema.0.label');
});

test('un formulario de PoC mal definido no se guarda y cada error se explica', function (array $campo, string $clave, string $mensaje) {
    $this->actingAs(propietarioDeEmpresa())->post(route('programas.store'), [
        'nombre' => 'Programa con PoC mal definida',
        'descripcion' => 'Descripcion',
        'objetivos' => [['tipo' => 'web', 'valor' => 'app.ejemplo.test']],
        'poc_schema' => $campo,
    ])->assertSessionHasErrors([$clave => $mensaje]);

    $this->assertDatabaseMissing('programas', ['nombre' => 'Programa con PoC mal definida']);
})->with([
    'identificador vacio' => [[['name' => '', 'label' => 'URL', 'type' => 'url']], 'poc_schema.0.name', 'Escribe el identificador del campo.'],
    'etiqueta vacia' => [[['name' => 'url', 'label' => '', 'type' => 'url']], 'poc_schema.0.label', 'Escribe la etiqueta que verá el investigador.'],
    'identificador con espacios' => [[['name' => 'mi campo', 'label' => 'Mi campo', 'type' => 'text']], 'poc_schema.0.name', 'El identificador solo puede tener minúsculas, números y guion bajo, y empezar por una letra (ej: url_afectada).'],
    'identificador repetido' => [[['name' => 'url', 'label' => 'A', 'type' => 'text'], ['name' => 'url', 'label' => 'B', 'type' => 'text']], 'poc_schema.0.name', 'Hay dos campos con el mismo identificador.'],
    'seleccion sin opciones' => [[['name' => 'metodo', 'label' => 'Metodo', 'type' => 'select']], 'poc_schema.0.options', 'Un campo de selección necesita al menos una opción.'],
    'opcion sin valor' => [[['name' => 'metodo', 'label' => 'Metodo', 'type' => 'select', 'options' => [['value' => '', 'label' => 'GET']]]], 'poc_schema.0.options.0.value', 'Cada opción necesita un valor.'],
]);

test('la empresa puede editar el poc_schema de su programa', function () {
    $user = propietarioDeEmpresa();
    $this->actingAs($user);
    $programa = programaDeEmpresa($user, [
        'poc_schema' => [['name' => 'antiguo', 'label' => 'Antiguo', 'type' => 'text']],
    ]);

    $response = $this->put(route('programas.update', $programa), [
        'nombre' => $programa->nombre,
        'descripcion' => $programa->descripcion,
        'poc_schema' => [
            ['name' => 'nuevo', 'label' => 'Nuevo campo', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    $response->assertRedirect();
    $programa->refresh();
    expect($programa->poc_schema)->toHaveCount(1)
        ->and($programa->poc_schema[0]['name'])->toBe('nuevo');
});
