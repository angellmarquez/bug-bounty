<?php

test('el usuario guarda su tema en la cuenta', function (string $tema) {
    $usuario = investigador();

    $this->actingAs($usuario)
        ->put(route('appearance.update'), ['tema' => $tema])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($usuario->fresh()->tema)->toBe($tema);
})->with(['terminal', 'corporativo', 'neon', 'auto']);

test('no se acepta un tema que no existe', function () {
    $usuario = investigador();

    $this->actingAs($usuario)
        ->put(route('appearance.update'), ['tema' => 'rosa-chicle'])
        ->assertSessionHasErrors('tema');

    expect($usuario->fresh()->tema)->toBeNull();
});

test('sin sesión no se puede guardar un tema', function () {
    $this->put(route('appearance.update'), ['tema' => 'neon'])->assertRedirect(route('login'));
});

test('la página se pinta con el tema de la cuenta, sin parpadeo', function () {
    $usuario = investigador(['tema' => 'corporativo']);

    $html = $this->actingAs($usuario)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('data-tema="corporativo"')
        ->and($html)->not->toMatch('/<html[^>]*class="dark"/');
});

test('el tema de la cuenta manda sobre la cookie del navegador', function () {
    $usuario = investigador(['tema' => 'neon']);

    $html = $this->actingAs($usuario)->withUnencryptedCookie('tema', 'corporativo')->get(route('dashboard'))->getContent();

    expect($html)->toContain('data-tema="neon"')
        ->and($html)->toMatch('/<html[^>]*class="dark"/');
});

test('sin sesión se usa el tema que recuerda la cookie', function () {
    expect($this->withUnencryptedCookie('tema', 'corporativo')->get(route('login'))->getContent())->toContain('data-tema="corporativo"');
});

test('sin sesión ni cookie se usa el tema Terminal', function () {
    expect($this->get(route('login'))->getContent())->toContain('data-tema="terminal"');
});

test('una cookie con un tema inventado se ignora', function () {
    expect($this->withUnencryptedCookie('tema', '"><script>alert(1)</script>')->get(route('login'))->getContent())
        ->toContain('data-tema="terminal"')
        ->not->toContain('<script>alert(1)</script>');
});
