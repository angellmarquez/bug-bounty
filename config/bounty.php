<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver de Verificación de Bounties Blockchain
    |--------------------------------------------------------------------------
    |
    | 'simulado': Para entorno local y tests. No requiere API key ni conexión externa.
    | 'polygon_amoy': Red oficial de pruebas de Polygon (USDC de prueba gratis).
    | 'polygon': Red principal Polygon Mainnet.
    |
    */
    'driver' => env('BOUNTY_DRIVER', 'simulado'),

    'api_key' => env('POLYGONSCAN_API_KEY', ''),

    'redes' => [
        'Polygon' => [
            'nombre' => 'Polygon Mainnet',
            'moneda' => 'USDC',
            'explorer_tx_url' => 'https://polygonscan.com/tx/',
            'api_url' => 'https://api.polygonscan.com/api',
            'token_contract' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
        ],
        'Polygon Amoy' => [
            'nombre' => 'Polygon Amoy (Testnet)',
            'moneda' => 'USDC',
            'explorer_tx_url' => 'https://amoy.polygonscan.com/tx/',
            'api_url' => 'https://api-amoy.polygonscan.com/api',
            'token_contract' => '0x41e94eb019c0762f9bfcf9fb1e58725bfb0e7582',
        ],
        'Arbitrum' => [
            'nombre' => 'Arbitrum One',
            'moneda' => 'USDC',
            'explorer_tx_url' => 'https://arbiscan.io/tx/',
            'api_url' => 'https://api.arbiscan.io/api',
            'token_contract' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831',
        ],
    ],
];
