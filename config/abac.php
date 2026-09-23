<?php

/*
|--------------------------------------------------------------------------
| Política ABAC de la plataforma Bug Bounty
|--------------------------------------------------------------------------
|
| Motor de reglas sobre atributos: cada regla evalúa atributos de sujeto
| (usuario), de objeto (recurso) y de entorno (contexto).
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
| Con deny_by_default activo, toda acción sin regla que la permita se deniega.
|
*/

return [

    'deny_by_default' => true,

    'reglas' => [

        // ------------------------------------------------------------------
        // 0. Bloqueo global por inactividad o suspensión (Prioridad 1)
        // ------------------------------------------------------------------
        [
            'id' => 'denegar-todo-a-usuario-inactivo',
            'prioridad' => 1,
            'acciones' => ['*'],
            'sujeto' => ['is_active' => ['=' => false]],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        // Una suspensión vigente lo bloquea todo salvo defenderse: ver su reputación
        // (sanciones incluidas) y apelar. Sin esa salida una sanción injusta sería definitiva.
        [
            'id' => 'denegar-todo-a-usuario-suspendido-salvo-apelar',
            'prioridad' => 1,
            'acciones' => [
                'reportes.*',
                'programas.*',
                'empresas.*',
                'moderacion.*',
                'moderadores.*',
                'usuarios.*',
                'sanciones.*',
                'auditoria.*',
                'config_reputacion.*',
                'claves_pgp_plataforma.*',
                'apelaciones.resolver',
            ],
            'sujeto' => ['suspendido' => ['=' => true]],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 0b. Bypass administrativo (supervisión y arbitraje)
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

        // El administrador no crea, no tría, no repara ni cierra en el día a día: supervisa y arbitra.
        [
            'id' => 'denegar-dia-a-dia-de-reportes-al-administrador',
            'prioridad' => 5,
            'acciones' => [
                'reportes.crear',
                'reportes.editar',
                'reportes.enviar',
                'reportes.eliminar',
                'reportes.asignar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.marcar_en_reparacion',
                'reportes.cerrar',
                'moderacion.ver',
            ],
            'sujeto' => ['roles' => ['contains' => 'administrador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 1. Separación Estricta de Funciones (SoD): Quién NO crea reportes
        // ------------------------------------------------------------------
        // Moderador / Empresa: DENEGAR SIEMPRE crear o enviar reportes.
        [
            'id' => 'denegar-crear-reportes-a-moderador',
            'prioridad' => 5,
            'acciones' => ['reportes.crear', 'reportes.enviar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-crear-reportes-a-empresa',
            'prioridad' => 5,
            'acciones' => ['reportes.crear', 'reportes.enviar'],
            'sujeto' => ['roles' => ['contains' => 'empresa']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 2. Investigador / Hacker: reportes propios y programas con acceso
        // ------------------------------------------------------------------
        [
            'id' => 'inv-crear-reporte-en-programa-publico-activo',
            'prioridad' => 20,
            'acciones' => ['reportes.crear'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'estado' => ['=' => 'activo'],
                'es_publico' => ['=' => true],
                'nivel_acceso' => ['in' => '@sujeto.niveles_acceso'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-crear-reporte-en-programa-privado-invitado',
            'prioridad' => 20,
            'acciones' => ['reportes.crear'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'estado' => ['=' => 'activo'],
                'es_publico' => ['=' => false],
                'invited_hacker_ids' => ['contains' => '@sujeto.id'],
            ],
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
            'id' => 'inv-descifrar-poc-propio',
            'prioridad' => 20,
            'acciones' => ['reportes.decrypt_poc'],
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
                'estado' => ['in' => ['borrador', 'enviado', 'needs_info']],
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
                'estado' => ['in' => ['borrador', 'needs_info']],
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
                'nivel_acceso' => ['in' => '@sujeto.niveles_acceso'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-ver-programa-privado-invitado',
            'prioridad' => 20,
            'acciones' => ['programas.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'es_publico' => ['=' => false],
                'estado' => ['in' => ['activo', 'en_pausa']],
                'invited_hacker_ids' => ['contains' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-ver-reputacion-propia',
            'prioridad' => 20,
            'acciones' => ['reputacion.ver'],
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
        // 3. Programas: quién crea y gestiona
        // ------------------------------------------------------------------
        // Crear programas corresponde exclusivamente a la empresa dueña (company_admin).
        [
            'id' => 'denegar-crear-editar-eliminar-o-publicar-programas-al-administrador',
            'prioridad' => 5,
            'acciones' => ['programas.crear', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'administrador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-crear-o-editar-programas-al-moderador',
            'prioridad' => 5,
            'acciones' => ['programas.crear', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-crear-programas-al-investigador',
            'prioridad' => 5,
            'acciones' => ['programas.crear'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 4. Empresa (company_admin)
        // ------------------------------------------------------------------
        [
            'id' => 'empresa-crear-programa',
            'prioridad' => 35,
            'acciones' => ['programas.crear'],
            'sujeto' => ['roles' => ['contains' => 'empresa']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-ver-programa-propio',
            'prioridad' => 35,
            'acciones' => ['programas.ver'],
            'sujeto' => ['roles' => ['contains' => 'empresa']],
            'objeto' => ['empresa_id' => ['=' => '@entorno.empresa_id']],
            'entorno' => ['empresa_id' => ['is_not_null']],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-gestionar-programa-propio',
            'prioridad' => 35,
            'acciones' => ['programas.gestionar', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => ['empresa_id' => ['=' => '@sujeto.empresa_id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-invitar-hacker-a-programa',
            'prioridad' => 35,
            'acciones' => ['programas.invitar_hacker'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => ['empresa_id' => ['=' => '@sujeto.empresa_id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // ABAC Clave: La empresa solo ve el reporte cuando su estado es triajado o resuelto.
        // Invisible para 'borrador', 'enviado' (new) y 'rechazado'.
        [
            'id' => 'empresa-ver-reportes-de-sus-programas',
            'prioridad' => 35,
            'acciones' => ['reportes.ver'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['en_revision', 'needs_info', 'validado', 'en_reparacion', 'cerrado']],
                'programa.empresa_id' => ['=' => '@sujeto.empresa_id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // La empresa puede descifrar el PoC una vez validado/en reparación/resuelto
        [
            'id' => 'empresa-descifrar-poc',
            'prioridad' => 35,
            'acciones' => ['reportes.decrypt_poc'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['validado', 'en_reparacion', 'cerrado']],
                'programa.empresa_id' => ['=' => '@sujeto.empresa_id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // Reparar y cerrar reportes de sus programas
        [
            'id' => 'empresa-reparar-y-cerrar-reportes-de-sus-programas',
            'prioridad' => 35,
            'acciones' => ['reportes.marcar_en_reparacion', 'reportes.cerrar'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['validado', 'en_reparacion']],
                'programa.empresa_id' => ['=' => '@sujeto.empresa_id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // ------------------------------------------------------------------
        // 5. Moderador: Programas y Triaje
        // ------------------------------------------------------------------
        [
            'id' => 'moderador-ver-sus-programas',
            'prioridad' => 35,
            'acciones' => ['programas.ver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => ['id' => ['in' => '@sujeto.programas_moderados']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-ver-reportes-de-sus-programas',
            'prioridad' => 35,
            'acciones' => ['reportes.ver', 'reportes.ver_notas_internas'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['!=' => 'borrador'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-descifrar-poc',
            'prioridad' => 35,
            'acciones' => ['reportes.decrypt_poc'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['!=' => 'borrador'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-ver-cola-de-moderacion',
            'prioridad' => 35,
            'acciones' => ['moderacion.ver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-resolver-apelaciones',
            'prioridad' => 30,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-asignar-reporte',
            'prioridad' => 35,
            'acciones' => ['reportes.asignar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['in' => ['enviado', 'en_revision', 'needs_info', 'validado']],
                'asignado_a' => ['is_null'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // Triaje: permitido en 'enviado' (new), 'en_revision' y 'needs_info'.
        [
            'id' => 'moderador-triaje',
            'prioridad' => 35,
            'acciones' => [
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
            ],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['in' => ['enviado', 'en_revision', 'needs_info']],
                'asignado_a' => ['is_null'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-triaje-asignado',
            'prioridad' => 35,
            'acciones' => ['reportes.validar', 'reportes.rechazar', 'reportes.marcar_duplicado'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['in' => ['enviado', 'en_revision', 'needs_info']],
                'asignado_a' => ['=' => '@sujeto.id'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],

        // Denegar explícitamente el triaje a investigadores
        [
            'id' => 'denegar-triaje-a-investigador',
            'prioridad' => 5,
            'acciones' => [
                'reportes.asignar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.marcar_en_reparacion',
                'reportes.cerrar',
            ],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 6. Conflicto de interés y juez/parte (el deny gana siempre)
        // ------------------------------------------------------------------
        [
            'id' => 'denegar-triaje-de-informe-propio',
            'prioridad' => 5,
            'acciones' => [
                'reportes.asignar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.marcar_en_reparacion',
                'reportes.cerrar',
                'reportes.ver_notas_internas',
            ],
            'sujeto' => ['autenticado' => ['=' => true]],
            'objeto' => ['investigador_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-resolver-apelacion-de-sancion-propia-al-moderador',
            'prioridad' => 5,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => ['sancion.aplicada_por' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-resolver-apelacion-propia',
            'prioridad' => 5,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['autenticado' => ['=' => true]],
            'objeto' => ['usuario_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'denegar',
        ],
    ],

];
