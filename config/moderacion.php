<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Límite de programas activos por moderador (Auto-Asignación)
    |--------------------------------------------------------------------------
    | Cantidad máxima de programas activos que el sistema asignará
    | automáticamente a un moderador para no sobrecargarlo de trabajo.
    | El administrador puede asignar más manualmente sin restricción.
    */
    'limite_programas_por_moderador' => (int) env('MODERACION_LIMITE_PROGRAMAS', 5),

    /*
    |--------------------------------------------------------------------------
    | Cantidad de moderadores por programa (Auto-Asignación)
    |--------------------------------------------------------------------------
    | Número deseado de moderadores asignados automáticamente a cada
    | programa activo (1 para equipo pequeño, 2 para redundancia de guardia).
    */
    'moderadores_por_programa' => (int) env('MODERACION_MODERADORES_POR_PROGRAMA', 2),
];
