<?php

/*
|--------------------------------------------------------------------------
| Motor de reputación y penalización
|--------------------------------------------------------------------------
|
| Configura la mecánica pedagógica de la plataforma: el ledger de reputación
| (tabla `ledger_reputacion`) es la única fuente de verdad del saldo y sirve
| tanto para premiar reportes válidos como para penalizar comportamientos
| fraudulentos (falsos positivos fabricados para inflar métricas).
|
| Las penalizaciones son proporcionales: a mayor gravedad y reincidencia,
| mayor descuento de puntos y mayor ventana de suspensión.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Saldo inicial
    |--------------------------------------------------------------------------
    |
    | Puntuación con la que arrancan los usuarios. Coherente con el default
    | `0` de la columna `users.reputation_score`.
    |
    */

    'puntos_inicial' => (int) env('REPUTACION_PUNTOS_INICIAL', 0),

    /*
    |--------------------------------------------------------------------------
    | Eventos de reputación positiva
    |--------------------------------------------------------------------------
    |
    | Puntos otorgados por eventos legítimos (otorgarPuntosEvento). Claves
    | usadas como `motivo` del asiento en el ledger.
    |
    */

    'puntos' => [
        // Se otorga cuando la empresa confirma un informe ya validado (en reparación o cerrado),
        // respetando el orden de llegada: la recompensa es para el primero que lo encontró.
        'reporte_validado' => (int) env('REPUTACION_PUNTOS_VALIDADO', 50),
        'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_RESUELTO', 100),
        'calidad_documentacion' => (int) env('REPUTACION_PUNTOS_CALIDAD', 10),
        'participacion' => (int) env('REPUTACION_PUNTOS_PARTICIPACION', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Puntos por severidad del reporte (Reparto justo automatizado)
    |--------------------------------------------------------------------------
    |
    | Cuando un evento de reputación corresponde a un reporte (reporte_validado
    | o reporte_resuelto), los puntos se calculan automáticamente según la
    | severidad técnica del hallazgo (CVSS v3.1).
    |
    | - Crítica: 100 validado / 200 resuelto (Total: 300)
    | - Alta:     50 validado / 100 resuelto (Total: 150)
    | - Media:    25 validado /  50 resuelto (Total: 75)
    | - Baja:     10 validado /  20 resuelto (Total: 30)
    | - Ninguna:   5 validado /  10 resuelto (Total: 15)
    |
    */

    'puntos_por_severidad' => [
        'critica' => [
            'reporte_validado' => (int) env('REPUTACION_PUNTOS_CRITICA_VALIDADO', 100),
            'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_CRITICA_RESUELTO', 200),
        ],
        'alta' => [
            'reporte_validado' => (int) env('REPUTACION_PUNTOS_ALTA_VALIDADO', 50),
            'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_ALTA_RESUELTO', 100),
        ],
        'media' => [
            'reporte_validado' => (int) env('REPUTACION_PUNTOS_MEDIA_VALIDADO', 25),
            'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_MEDIA_RESUELTO', 50),
        ],
        'baja' => [
            'reporte_validado' => (int) env('REPUTACION_PUNTOS_BAJA_VALIDADO', 10),
            'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_BAJA_RESUELTO', 20),
        ],
        'ninguna' => [
            'reporte_validado' => (int) env('REPUTACION_PUNTOS_NINGUNA_VALIDADO', 5),
            'reporte_resuelto' => (int) env('REPUTACION_PUNTOS_NINGUNA_RESUELTO', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rangos de reputación
    |--------------------------------------------------------------------------
    |
    | Como en un programa de niveles: cada rango empieza en `minimo` puntos y dura hasta el
    | siguiente. El rango se calcula siempre a partir del saldo del ledger, no se guarda.
    |
    */

    'rangos' => [
        'bronce' => ['nombre' => 'Bronce', 'minimo' => 0],
        'plata' => ['nombre' => 'Plata', 'minimo' => 100],
        'oro' => ['nombre' => 'Oro', 'minimo' => 300],
        'platino' => ['nombre' => 'Platino', 'minimo' => 700],
        'diamante' => ['nombre' => 'Diamante', 'minimo' => 1500],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nivel de acceso de los programas
    |--------------------------------------------------------------------------
    |
    | Al crear un programa la empresa elige un nivel (bajo, medio, alto) y cada nivel exige
    | un rango mínimo. Los rangos superiores (platino, diamante) también entran a todos.
    |
    */

    'acceso' => [
        'bajo' => 'bronce',
        'medio' => 'plata',
        'alto' => 'oro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Penalización base por gravedad
    |--------------------------------------------------------------------------
    |
    | Valores negativos de la sanción antes de aplicar el multiplicador de
    | reincidencia.
    |
    */

    'penalizacion' => [
        'leve' => (int) env('REPUTACION_PENALIZACION_LEVE', -25),
        'media' => (int) env('REPUTACION_PENALIZACION_MEDIA', -80),
        'grave' => (int) env('REPUTACION_PENALIZACION_GRAVE', -250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ventana de suspensión por gravedad
    |--------------------------------------------------------------------------
    |
    | Días que el investigador queda suspendido. `0` significa sin suspensión
    | (la sanción es solo reputacional).
    |
    */

    'suspension' => [
        'leve' => ['dias' => 0],
        'media' => ['dias' => (int) env('REPUTACION_SUSPENSION_MEDIA_DIAS', 7)],
        'grave' => ['dias' => (int) env('REPUTACION_SUSPENSION_GRAVE_DIAS', 30)],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plazo de apelación
    |--------------------------------------------------------------------------
    |
    | Días desde la aplicación de la sanción durante los que el investigador
    | puede presentar apelación (regla ABAC `apelaciones.crear`).
    |
    */

    'plazo_apelacion_dias' => (int) env('REPUTACION_PLAZO_APELACION_DIAS', 7),

    /*
    |--------------------------------------------------------------------------
    | Detección de trampas (CheatDetectionService)
    |--------------------------------------------------------------------------
    */

    'cheat' => [

        /*
        | Ráfaga: más reportes de los permitidos enviados en una misma ventana
        | de minutos (inflado de métricas por volumen).
        */
        'rafaga' => [
            'ventana_minutos' => (int) env('REPUTACION_RAFAGA_VENTANA', 15),
            'max_reportes' => (int) env('REPUTACION_RAFAGA_MAX', 5),
        ],

        /*
        | Duplicados: tasa de reportes marcados como duplicados sobre los
        | reportes enviados. Requiere un mínimo absoluto para evitar falsos
        | positivos con poca muestra.
        */
        'duplicados' => [
            'minimo' => (int) env('REPUTACION_DUPLICADOS_MINIMO', 3),
            'tasa_max' => (float) env('REPUTACION_DUPLICADOS_TASA_MAX', 0.5),
        ],

        /*
        | Fabricación: reportes de severidad alta/crítica que, terminando en un
        | estado sospechoso (duplicado, fuera de alcance o rechazado), no
        | incorporan una PoC válida. Señal de falsos positivos fabricados para
        | inflar la reputación.
        */
        'fabricacion' => [
            'severidades' => ['alta', 'critica'],
            'requiere_poc' => true,
            'estados_sospechosos' => ['duplicado', 'fuera_de_alcance', 'rechazado'],
        ],

        /*
        | Reincidencia: multiplicador proporcional aplicado a la penalización
        | base según las sanciones vigentes previas del usuario.
        */
        'recidivismo' => [
            'multiplicador' => (float) env('REPUTACION_RECIDIVA_MULTIPLICADOR', 0.5),
            'max_multiplicador' => (float) env('REPUTACION_RECIDIVA_MAX', 3.0),
        ],

        /*
        | Ventana de idempotencia para detecciones agregadas (ráfaga y tasa de
        | duplicados): evita sancionar dos veces la misma conducta si ejecutar()
        | se invoca varias veces.
        */
        'idempotencia_minutos' => (int) env('REPUTACION_IDEMPOTENCIA_MINUTOS', 1440),

    ],

];
