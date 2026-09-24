<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = \App\Models\Programa::find(9);
if (!$p) {
    echo "Programa 9 not found\n";
    exit;
}

echo "Programa 9: {$p->nombre}\n";
echo "empresa_id: " . ($p->empresa_id ?? 'null') . "\n";
echo "descripcion encrypted: " . (str_starts_with($p->descripcion, '-----BEGIN PGP') ? 'YES' : 'NO') . "\n";

$pgp = app(\App\Services\Pgp\PgpService::class);
echo "PGP Driver: " . $pgp->driver()->name() . "\n";

try {
    $desc = $pgp->descifrarPrograma($p->descripcion, $p->bugs_buscados, $p);
    echo "Programa descifrado OK!\n";
    echo "Descripcion: " . $desc['descripcion'] . "\n";
    echo "Bugs buscados: " . $desc['bugs_buscados'] . "\n";
} catch (\Throwable $e) {
    echo "Error descifrando programa: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}

foreach ($p->objetivos as $o) {
    echo "Objetivo #{$o->id} (" . ($o->tipo->value ?? $o->tipo) . "): ";
    try {
        $do = $pgp->descifrarObjetivo($o->valor, $o->descripcion, $o);
        echo "OK! Valor: {$do['valor']}\n";
    } catch (\Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        if ($e->getPrevious()) {
            echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
        }
    }
}

$r = \App\Models\Reporte::find(11);
if ($r) {
    echo "\nReporte 11: {$r->numero_reporte}\n";
    try {
        $repPgp = $pgp->descifrarReporte((string) $r->descripcion, $r->poc, $r);
        echo "Reporte 11 descifrado OK!\n";
        echo "Descripcion: " . substr($repPgp['descripcion'], 0, 40) . "\n";
        echo "Poc: " . json_encode($repPgp['poc']) . "\n";
    } catch (\Throwable $e) {
        echo "Error descifrando reporte 11: " . $e->getMessage() . "\n";
        if ($e->getPrevious()) {
            echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
        }
    }
}
