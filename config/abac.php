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

        // El administrador no crea, no tría, no repara ni cierra en el día a día: supervisa, arbitra
        // y reparte el trabajo (asignar un informe a un moderador es solo suyo).
        // NOTA ARQUITECTÓNICA: esta regla es intencional y anula admin-bypass-total para estas
        // acciones operativas. Deny-Overrides garantiza que el deny gane. Si en el futuro se
        // necesita SoD por recurso para el admin, cambiar a condición de objeto en lugar de eliminarla.
        [
            'id' => 'denegar-dia-a-dia-de-reportes-al-administrador',
            'prioridad' => 5,
            'acciones' => [
                'reportes.crear',
                'reportes.editar',
                'reportes.enviar',
                'reportes.eliminar',
                'reportes.revisar',
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
        // Moderador: SoD global — decisión de diseño del sistema: una vez que un
        // usuario tiene el rol moderador, no puede reportar en ningún programa.
        // Esto evita que un moderador use su acceso privilegiado para crear reportes
        // manipulados. Para SoD por recurso (solo bloquear en programas que modera),
        // cambiar 'objeto' => [] por 'objeto' => ['id' => ['in' => '@sujeto.programas_moderados']].
        [
            'id' => 'denegar-crear-reportes-a-moderador',
            'prioridad' => 5,
            'acciones' => ['reportes.crear', 'reportes.enviar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        // Empresa: denegación global — la empresa es la contraparte pagadora;
        // nunca puede reportar vulnerabilidades en ningún programa (SoD absoluto).

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
        // ABAC Clave: la empresa solo ve un informe cuando moderación ya lo decidió: aprobado
        // (validado, en reparación, cerrado) o descartado (rechazado, duplicado, fuera de alcance).
        // Nunca un borrador ni uno aún en revisión ('enviado', 'en_revision', 'needs_info').
        [
            'id' => 'empresa-ver-reportes-de-sus-programas',
            'prioridad' => 35,
            'acciones' => ['reportes.ver'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['validado', 'en_reparacion', 'cerrado', 'rechazado', 'duplicado', 'fuera_de_alcance']],
                'programa.empresa_id' => ['=' => '@sujeto.empresa_id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // La empresa descifra el PoC de lo que puede ver: aprobado o descartado por moderación
        [
            'id' => 'empresa-descifrar-poc',
            'prioridad' => 35,
            'acciones' => ['reportes.decrypt_poc'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['validado', 'en_reparacion', 'cerrado', 'rechazado', 'duplicado', 'fuera_de_alcance']],
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
        // Cola por orden de llegada: el moderador solo abre (y descifra) el informe que le toca,
        // el enviado más antiguo aún sin revisor, y los que ya tomó. Los demás del programa
        // quedan fuera de su vista: ni el siguiente en la fila ni los que revisa otro moderador.
        [
            'id' => 'moderador-ver-reportes-que-tomo',
            'prioridad' => 35,
            'acciones' => ['reportes.ver', 'reportes.ver_notas_internas', 'reportes.decrypt_poc'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['!=' => 'borrador'],
                'asignado_a' => ['=' => '@sujeto.id'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
                'investigador_id' => ['!=' => '@sujeto.id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-ver-siguiente-de-la-cola',
            'prioridad' => 35,
            'acciones' => ['reportes.ver', 'reportes.ver_notas_internas', 'reportes.decrypt_poc', 'reportes.revisar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'siguiente_en_cola' => ['=' => true],
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
        // Triaje: el moderador toma el siguiente de la cola con "Iniciar revisión" (queda asignado
        // a él) y desde entonces solo él lo tría. Asignar/reasignar a otro es exclusivo del admin.
        [
            'id' => 'moderador-triaje-asignado',
            'prioridad' => 35,
            'acciones' => ['reportes.revisar', 'reportes.validar', 'reportes.rechazar', 'reportes.marcar_duplicado'],
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
                'reportes.revisar',
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
                'reportes.revisar',
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
            'id' => 'denegar-resolver-apelaciones-a-moderador',
            'prioridad' => 5,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
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
