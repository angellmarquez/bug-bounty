<?php

/*
|--------------------------------------------------------------------------
| Plan Profesional pagado en USDC
|--------------------------------------------------------------------------
|
| La empresa paga desde su wallet directamente a la TESORERÍA del proyecto y la
| plataforma verifica el pago en la blockchain (misma red y mismo verificador que
| los bounties: config/bounty.php). El servidor solo conoce la DIRECCIÓN pública
| de la tesorería: no puede mover sus fondos. Guárdala en una wallet física o en
| una multifirma (Safe) y nunca pongas su clave privada ni su frase en el servidor.
| Ver docs/plan-profesional.md.
|
*/

return [

    // Dirección pública (0x…) de la tesorería del proyecto. Sin ella no se puede pagar el plan.
    'tesoreria' => env('PLAN_TESORERIA_WALLET'),

    'profesional' => [
        'precio_usdc' => (float) env('PLAN_PROFESIONAL_PRECIO_USDC', 5),
        'dias' => (int) env('PLAN_PROFESIONAL_DIAS', 30),
    ],

    // Cuántos días antes del vencimiento se avisa a la empresa para que renueve.
    'aviso_dias_antes' => (int) env('PLAN_AVISO_DIAS_ANTES', 5),
];
