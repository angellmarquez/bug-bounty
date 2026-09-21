<?php

/*
|--------------------------------------------------------------------------
| Límites de envío de informes (protección contra envíos masivos)
|--------------------------------------------------------------------------
|
| Se aplican al ENVIAR un informe, no al guardar borradores: un borrador no
| llega a la empresa ni a los moderadores. Los límites globales (informes en
| total por investigador) reutilizan la ráfaga de `reputacion.cheat.rafaga`,
| de modo que el bloqueo salta justo en el umbral en que la auditoría
| sancionaría, y un investigador legítimo nunca llega a ser sancionado.
|
*/

return [

    'limites_envio' => [

        /*
        | Máximo de informes enviados por un mismo investigador a un mismo
        | programa dentro de la ventana.
        */
        'por_programa' => [
            'maximo' => (int) env('REPORTES_MAX_POR_PROGRAMA', 3),
            'ventana_minutos' => (int) env('REPORTES_VENTANA_PROGRAMA', 60),
        ],

        /*
        | Un mismo investigador no puede enviar dos informes con el mismo
        | título al mismo programa dentro de estas horas.
        */
        'titulo_repetido_horas' => (int) env('REPORTES_TITULO_REPETIDO_HORAS', 24),

        /*
        | Peticiones por minuto y usuario a las rutas de guardar/enviar
        | informes (frena scripts que disparan cientos de peticiones).
        */
        'peticiones_por_minuto' => (int) env('REPORTES_PETICIONES_POR_MINUTO', 20),
    ],

];
