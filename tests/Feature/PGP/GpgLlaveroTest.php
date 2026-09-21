<?php

use App\Services\Pgp\DataObjects\PgpKeyInfo;
use App\Services\Pgp\Drivers\GpgBinaryDriver;

/**
 * Listado real (`gpg --with-colons --list-keys`) de un llavero con DOS claves: la vieja, con sus
 * dos subclaves de cifrado, y otra creada después. Reproduce el fallo por el que el driver tomaba
 * «la primera clave» del llavero en lugar de la que acababa de generar.
 */
function listadoConDosClaves(): string
{
    return implode("\n", [
        'tru::1:1789996250:1821532252:3:1:5',
        'pub:u:255:22:C42D666E8292A599:1789996252:1821532252::u:::scESC:::::ed25519:::0:',
        'fpr:::::::::689BA794E7A69BB3D32A5ABCC42D666E8292A599:',
        'uid:u::::1789996252::05A08302D22F036521C64D2C236A244FDEA7863C::Plataforma BugBounty <seguridad@localhost>::::::::::0:',
        'sub:u:255:18:BF9E829B9FF050F5:1789996253:1821532253:::::e:::::cv25519::',
        'fpr:::::::::B2897F81F5F4944885160F38BF9E829B9FF050F5:',
        'sub:u:255:18:D33E3C3FBF30591F:1789999956:1821535956:::::e:::::cv25519::',
        'fpr:::::::::1826645FACBECC2B41AC7A42D33E3C3FBF30591F:',
        'pub:u:255:22:C7A936029B61AF90:1789999950:1821535950::u:::scSC:::::ed25519:::0:',
        'fpr:::::::::D4916FBACAC121031942AC3AC7A936029B61AF90:',
        'uid:u::::1789999950::F5F8E0AFD4F6E1DC1EC57056CDCC8539BE0C3BDA::Plataforma BugBounty (auto 20260921-kda3) <seguridad@localhost>::::::::::0:',
        '',
    ]);
}

test('el listado del llavero se interpreta clave por clave e ignora las subclaves', function () {
    $claves = GpgBinaryDriver::parsearListado(listadoConDosClaves());

    expect($claves)->toHaveCount(2)
        ->and($claves[0]->fingerprint)->toBe('689BA794E7A69BB3D32A5ABCC42D666E8292A599')
        ->and($claves[0]->idClave)->toBe('C42D666E8292A599')
        ->and($claves[1]->fingerprint)->toBe('D4916FBACAC121031942AC3AC7A936029B61AF90')
        ->and($claves[1]->idClave)->toBe('C7A936029B61AF90')
        ->and($claves[1]->bits)->toBe(255)
        ->and($claves[1]->creadaEn)->not->toBeNull()
        ->and($claves[1]->expiraEn)->not->toBeNull();
});

test('un llavero vacio o una salida vacia no da claves', function () {
    expect(GpgBinaryDriver::parsearListado(''))->toBe([])
        ->and(GpgBinaryDriver::parsearListado("tru::1:1789996250:0:3:1:5\n"))->toBe([]);
});

test('la clave recien generada es la que no estaba antes, no la primera del llavero', function () {
    $despues = GpgBinaryDriver::parsearListado(listadoConDosClaves());

    $nueva = GpgBinaryDriver::claveNueva(['689BA794E7A69BB3D32A5ABCC42D666E8292A599'], $despues);

    expect($nueva)->toBeInstanceOf(PgpKeyInfo::class)
        ->and($nueva->fingerprint)->toBe('D4916FBACAC121031942AC3AC7A936029B61AF90')
        ->and($nueva->fingerprint)->not->toBe($despues[0]->fingerprint);
});

test('con un llavero vacio la unica clave es la nueva', function () {
    $despues = GpgBinaryDriver::parsearListado(listadoConDosClaves());

    expect(GpgBinaryDriver::claveNueva([], [$despues[0]])->fingerprint)->toBe('689BA794E7A69BB3D32A5ABCC42D666E8292A599');
});

test('si no aparece ninguna clave nueva, o aparecen varias, no se adivina cual es', function () {
    $todas = GpgBinaryDriver::parsearListado(listadoConDosClaves());
    $huellas = array_map(fn (PgpKeyInfo $c) => $c->fingerprint, $todas);

    expect(GpgBinaryDriver::claveNueva($huellas, $todas))->toBeNull()
        ->and(GpgBinaryDriver::claveNueva([], $todas))->toBeNull();
});
