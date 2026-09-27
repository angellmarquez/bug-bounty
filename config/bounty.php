<?php

/*
|--------------------------------------------------------------------------
| Recompensas (bounties) en USDC
|--------------------------------------------------------------------------
|
| La plataforma NO custodia fondos: la empresa paga desde su propia wallet y la
| plataforma solo verifica en la blockchain que el pago ocurrió (token USDC oficial,
| wallet del investigador, monto y confirmaciones) leyendo el recibo por JSON-RPC.
|
| Todos los pagos van por una sola red, la activa (`BOUNTY_RED`). Por defecto es la
| testnet Polygon Amoy, con USDC de prueba gratuito. Mainnet (dinero real) queda
| bloqueada salvo que se active a propósito con BOUNTY_PERMITIR_MAINNET=true.
|
*/

return [

    'red' => env('BOUNTY_RED', 'polygon_amoy'),

    'permitir_mainnet' => (bool) env('BOUNTY_PERMITIR_MAINNET', false),

    // Tras este tiempo sin que la transacción aparezca en la red, el pago se da por fallido.
    'verificacion_expira_minutos' => (int) env('BOUNTY_VERIFICACION_EXPIRA_MINUTOS', 30),

    'rpc_timeout_segundos' => 10,

    // Transfer(address,address,uint256): el evento que emite un token ERC-20 al transferir.
    'topic_transfer' => '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',

    'redes' => [
        'polygon_amoy' => [
            'nombre' => 'Polygon Amoy (testnet)',
            'testnet' => true,
            'chain_id' => 80002,
            'rpc_url' => env('BOUNTY_RPC_URL_AMOY', 'https://polygon-amoy-bor-rpc.publicnode.com'),
            'explorer_url' => 'https://amoy.polygonscan.com',
            'moneda_nativa' => ['name' => 'POL', 'symbol' => 'POL', 'decimals' => 18],
            // USDC de prueba de Circle en Amoy (se obtiene gratis en https://faucet.circle.com).
            'usdc' => '0x41E94Eb019C0762f9Bfcf9Fb1E58725BfB0e7582',
            'decimales' => 6,
            'confirmaciones' => (int) env('BOUNTY_CONFIRMACIONES_AMOY', 3),
            'faucets' => [
                'usdc' => 'https://faucet.circle.com',
                'gas' => 'https://faucet.polygon.technology',
            ],
        ],
        'polygon' => [
            'nombre' => 'Polygon PoS (mainnet)',
            'testnet' => false,
            'chain_id' => 137,
            'rpc_url' => env('BOUNTY_RPC_URL_POLYGON', 'https://polygon-bor-rpc.publicnode.com'),
            'explorer_url' => 'https://polygonscan.com',
            'moneda_nativa' => ['name' => 'POL', 'symbol' => 'POL', 'decimals' => 18],
            // USDC nativo de Circle en Polygon PoS.
            'usdc' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'decimales' => 6,
            'confirmaciones' => (int) env('BOUNTY_CONFIRMACIONES_POLYGON', 30),
            'faucets' => [],
        ],
    ],
];
