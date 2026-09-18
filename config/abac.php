<?php

/*
|--------------------------------------------------------------------------
| Política ABAC de la plataforma
|--------------------------------------------------------------------------
|
| Motor de reglas sobre atributos (no permisos por rol): cada regla evalúa
| atributos de sujeto (usuario), de objeto (recurso) y de entorno (contexto).
|
| Estructura de regla:
|   id         -> identificador único (aparece en auditoría y trazas)
|   prioridad  -> orden de evaluación (menor primero); el deny siempre gana
|   acciones   -> lista de acciones (comodines: 'reportes.*' y '*')
|   sujeto     -> condiciones sobre atributos del usuario
|   objeto     -> condiciones sobre atributos del recurso (rutas de relación ok)
|   entorno    -> condiciones sobre el entorno (app_env, ahora)
|   decision   -> 'permitir' | 'denegar'
|
| Operadores soportados:
|   =  !=  in  not_in  contains  >  >=  <  <=  is_null  is_not_null
|
| Referencias a otros atributos en los valores esperados:
|   @sujeto.id, @sujeto.roles, @objeto.x, @entorno.ahora, ...
|
| Con deny_by_default activo, toda acción sin regla que la permita se deniega.
|
*/

return [

    'deny_by_default' => true,

    'reglas' => [

        // ------------------------------------------------------------------
        // 0. Bypass administrativo. Atributiva (roles contiene administrador).
        // ------------------------------------------------------------------
        [
            'id' => 'admin-bypass-total',
            'prioridad' => 10,
            'acciones' => ['*'],
            'sujeto' => ['roles' => ['contains' => 'administrador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // ------------------------------------------------------------------
        // 1. Investigador: reportes propios y programas públicos.
        // ------------------------------------------------------------------
        [
            'id' => 'inv-crear-reporte-en-programa-activo',
            'prioridad' => 20,
            'acciones' => ['reportes.crear'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => ['estado' => ['=' => 'activo']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-ver-reporte-propio',
            'prioridad' => 20,
            'acciones' => ['reportes.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => ['investigador_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-editar-reporte-propio-en-colada',
            'prioridad' => 20,
            'acciones' => ['reportes.editar'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'investigador_id' => ['=' => '@sujeto.id'],
                'estado' => ['in' => ['borrador', 'enviado']],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-enviar-reporte-borrador',
            'prioridad' => 20,
            'acciones' => ['reportes.enviar'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'investigador_id' => ['=' => '@sujeto.id'],
                'estado' => ['=' => 'borrador'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-eliminar-reporte-borrador',
            'prioridad' => 20,
            'acciones' => ['reportes.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'investigador_id' => ['=' => '@sujeto.id'],
                'estado' => ['=' => 'borrador'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-ver-programa-publico',
            'prioridad' => 20,
            'acciones' => ['programas.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'es_publico' => ['=' => true],
                'estado' => ['in' => ['activo', 'en_pausa']],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // ------------------------------------------------------------------
        // 2. Investigador: claves PGP propias y apelaciones propias en plazo.
        // ------------------------------------------------------------------
        [
            'id' => 'inv-gestionar-clave-propia',
            'prioridad' => 20,
            'acciones' => ['claves_pgp.ver', 'claves_pgp.revocar'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => ['usuario_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-registrar-y-verificar-clave',
            'prioridad' => 20,
            'acciones' => ['claves_pgp.registrar', 'claves_pgp.verificar'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-apelar-sancion-propia-en-plazo',
            'prioridad' => 20,
            'acciones' => ['apelaciones.crear'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'sancion.usuario_id' => ['=' => '@sujeto.id'],
                'sancion.estado' => ['in' => ['aplicada', 'apelada']],
                'sancion.plazo_apelacion' => ['>=' => '@entorno.ahora'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // ------------------------------------------------------------------
        // 3. Gestión: triaje de reportes y operación de programas.
        // ------------------------------------------------------------------
        [
            'id' => 'gestion-ver-reportes-no-borrador',
            'prioridad' => 30,
            'acciones' => ['reportes.ver', 'reportes.ver_notas_internas'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => ['estado' => ['!=' => 'borrador']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-asignar-reportes',
            'prioridad' => 30,
            'acciones' => ['reportes.asignar'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => ['estado' => ['in' => ['enviado', 'en_revision']]],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-triaje-propio',
            'prioridad' => 30,
            'acciones' => [
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.pagar',
                'reportes.cerrar',
            ],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => [
                'estado' => ['in' => ['enviado', 'en_revision', 'validado', 'en_reparacion', 'pago_pendiente']],
                'asignado_a' => ['=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-triaje-sin-asignar',
            'prioridad' => 30,
            'acciones' => [
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.pagar',
                'reportes.cerrar',
            ],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => [
                'estado' => ['in' => ['enviado', 'en_revision', 'validado', 'en_reparacion', 'pago_pendiente']],
                'asignado_a' => ['is_null'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-crear-programa',
            'prioridad' => 30,
            'acciones' => ['programas.crear'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-programa-propio',
            'prioridad' => 30,
            'acciones' => ['programas.gestionar', 'programas.cambiar_estado'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => ['creado_por' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-ver-todas-claves',
            'prioridad' => 30,
            'acciones' => ['claves_pgp.ver', 'claves_pgp.verificar'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'gestion-resolver-apelaciones',
            'prioridad' => 30,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['roles' => ['contains' => 'gestion']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // ------------------------------------------------------------------
        // 4. Deniega explícitamente el triaje a investigadores puros (aunque
        //    otra regla coincidiera, el deny gana). La igualdad exacta evita
        //    penalizar a perfiles mixtos con rol de gestión.
        // ------------------------------------------------------------------
        [
            'id' => 'denegar-triaje-a-investigador',
            'prioridad' => 5,
            'acciones' => [
                'reportes.asignar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.pagar',
                'reportes.cerrar',
            ],
            'sujeto' => ['roles' => ['=' => ['investigador']]],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

    ],

];
