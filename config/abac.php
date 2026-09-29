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
|   descripcion-> qué dice la regla en lenguaje llano (la muestra el simulador ABAC)
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
            'descripcion' => 'Una cuenta desactivada por el administrador no puede hacer nada.',
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
            'descripcion' => 'Un usuario suspendido queda bloqueado en todo, salvo ver su reputación y apelar la sanción.',
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
                'config_suscripcion.*',
                'ingresos.*',
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
            'descripcion' => 'El administrador puede supervisar y arbitrar todo (salvo lo que otras reglas le prohíben).',
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
            'descripcion' => 'El administrador supervisa, pero no crea ni tría informes: eso es trabajo de investigadores y moderadores.',
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
                'reportes.ajustar_cvss',
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
            'descripcion' => 'Un moderador no envía informes: no puede ser juez y parte.',
            'prioridad' => 5,
            'acciones' => ['reportes.crear', 'reportes.enviar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        // Fuera del periodo del programa (antes de su inicio o después de su fin) no se reciben
        // informes aunque el programa siga "activo": el estado se actualiza con el scheduler, pero
        // la fecha manda desde el primer momento.
        [
            'id' => 'denegar-reportar-programa-fuera-de-fechas',
            'descripcion' => 'No se puede reportar en un programa que aún no empezó o que ya terminó.',
            'prioridad' => 5,
            'acciones' => ['reportes.crear'],
            'sujeto' => ['autenticado' => ['=' => true]],
            'objeto' => ['fuera_de_fechas' => ['=' => true]],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-enviar-informe-a-programa-fuera-de-fechas',
            'descripcion' => 'No se puede enviar un informe a un programa fuera de sus fechas.',
            'prioridad' => 5,
            'acciones' => ['reportes.enviar'],
            'sujeto' => ['autenticado' => ['=' => true]],
            'objeto' => ['programa.fuera_de_fechas' => ['=' => true]],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        // Empresa: denegación global — la empresa es la contraparte pagadora;
        // nunca puede reportar vulnerabilidades en ningún programa (SoD absoluto).

        [
            'id' => 'denegar-crear-reportes-a-empresa',
            'descripcion' => 'Una empresa nunca envía informes: es quien los recibe y paga.',
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
            'id' => 'denegar-no-verificado-en-programa-premium',
            'descripcion' => 'Los programas «solo verificados» no aceptan informes de investigadores sin verificar.',
            'prioridad' => 15,
            'acciones' => ['reportes.crear'],
            'sujeto' => [
                'roles' => ['contains' => 'investigador'],
                'es_verificado' => ['=' => false],
            ],
            'objeto' => [
                'solo_verificados' => ['=' => true],
            ],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'inv-crear-reporte-en-programa-publico-activo',
            'descripcion' => 'Un investigador puede reportar en un programa público y activo si su rango le da acceso a ese nivel.',
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
            'descripcion' => 'Un investigador puede reportar en un programa privado y activo solo si fue invitado.',
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
            'descripcion' => 'Un investigador puede ver sus propios informes.',
            'prioridad' => 20,
            'acciones' => ['reportes.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => ['investigador_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-descifrar-poc-propio',
            'descripcion' => 'Un investigador puede descifrar la prueba de concepto de sus propios informes.',
            'prioridad' => 20,
            'acciones' => ['reportes.decrypt_poc'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => ['investigador_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-editar-reporte-propio-en-colada',
            'descripcion' => 'Un investigador puede editar su informe mientras está en borrador, enviado o pendiente de información.',
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
            'descripcion' => 'Un investigador puede enviar su informe si está en borrador o le pidieron más información.',
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
            'descripcion' => 'Un investigador puede eliminar su informe solo mientras es un borrador.',
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
            'descripcion' => 'Un investigador ve los programas públicos (activos o en pausa) de su nivel de acceso.',
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
            'descripcion' => 'Un investigador ve un programa privado solo si fue invitado.',
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
            'descripcion' => 'Un investigador puede ver su propia reputación.',
            'prioridad' => 20,
            'acciones' => ['reputacion.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'inv-apelar-sancion-propia-en-plazo',
            'descripcion' => 'Un investigador puede apelar una sanción suya mientras siga vigente el plazo de apelación.',
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
            'descripcion' => 'El administrador no crea ni edita programas: son de las empresas.',
            'prioridad' => 5,
            'acciones' => ['programas.crear', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'administrador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-crear-o-editar-programas-al-moderador',
            'descripcion' => 'Un moderador no crea ni edita programas: solo revisa informes.',
            'prioridad' => 5,
            'acciones' => ['programas.crear', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-crear-programas-al-investigador',
            'descripcion' => 'Un investigador no puede crear programas.',
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
            'descripcion' => 'Una empresa puede crear programas.',
            'prioridad' => 35,
            'acciones' => ['programas.crear'],
            'sujeto' => ['roles' => ['contains' => 'empresa']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-ver-programa-propio',
            'descripcion' => 'Una empresa ve sus propios programas, aunque estén en borrador o en pausa.',
            'prioridad' => 35,
            'acciones' => ['programas.ver'],
            'sujeto' => ['roles' => ['contains' => 'empresa']],
            'objeto' => ['empresa_id' => ['=' => '@entorno.empresa_id']],
            'entorno' => ['empresa_id' => ['is_not_null']],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-gestionar-programa-propio',
            'descripcion' => 'Una empresa gestiona (edita, publica, pausa, elimina) solo los programas de su propia empresa.',
            'prioridad' => 35,
            'acciones' => ['programas.gestionar', 'programas.editar', 'programas.cambiar_estado', 'programas.eliminar'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => ['empresa_id' => ['=' => '@sujeto.empresa_id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-invitar-hacker-a-programa',
            'descripcion' => 'Una empresa invita investigadores solo a sus propios programas.',
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
            'descripcion' => 'Una empresa ve los informes de sus programas solo cuando moderación ya los decidió (aprobados o descartados).',
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
            'descripcion' => 'Una empresa descifra la prueba de concepto de los informes de sus programas ya decididos por moderación.',
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
            'descripcion' => 'Una empresa marca en reparación y cierra los informes validados de sus programas.',
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
        // La empresa paga su propio Plan Profesional (nunca el de otra).
        [
            'id' => 'empresa-pagar-su-plan',
            'descripcion' => 'Una empresa paga solo su propio Plan Profesional.',
            'prioridad' => 35,
            'acciones' => ['empresas.pagar_plan'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => ['id' => ['=' => '@sujeto.empresa_id']],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        // Gestionar y pagar recompensas (bounties) por la empresa dueña del programa
        [
            'id' => 'empresa-gestionar-y-pagar-bounties',
            'descripcion' => 'Una empresa asigna y paga recompensas de los informes validados de sus programas.',
            'prioridad' => 35,
            'acciones' => ['reportes.asignar_bounty', 'reportes.pagar_bounty'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['in' => ['validado', 'en_reparacion', 'cerrado']],
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
            'descripcion' => 'Un moderador ve los programas que tiene asignados.',
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
            'descripcion' => 'Un moderador ve los informes que él mismo tomó, de sus programas y que no escribió él.',
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
            'descripcion' => 'Un moderador puede abrir y tomar el siguiente informe de la cola de sus programas.',
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
            'descripcion' => 'Un moderador puede entrar a su cola de moderación.',
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
            'descripcion' => 'Un moderador tría (valida, rechaza, marca duplicado, ajusta CVSS) solo los informes en revisión que tiene asignados, de sus programas y que no escribió él.',
            'prioridad' => 35,
            'acciones' => ['reportes.revisar', 'reportes.validar', 'reportes.rechazar', 'reportes.marcar_duplicado', 'reportes.ajustar_cvss'],
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
            'descripcion' => 'Un investigador nunca tría informes.',
            'prioridad' => 5,
            'acciones' => [
                'reportes.asignar',
                'reportes.revisar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.ajustar_cvss',
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
            'descripcion' => 'Nadie puede triar su propio informe: no se puede ser juez y parte.',
            'prioridad' => 5,
            'acciones' => [
                'reportes.asignar',
                'reportes.revisar',
                'reportes.validar',
                'reportes.rechazar',
                'reportes.marcar_duplicado',
                'reportes.ajustar_cvss',
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
            'descripcion' => 'Un moderador no resuelve apelaciones: podría estar revisando su propia sanción.',
            'prioridad' => 5,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [],
            'entorno' => [],
            'decision' => 'denegar',
        ],
        [
            'id' => 'denegar-resolver-apelacion-propia',
            'descripcion' => 'Nadie resuelve su propia apelación.',
            'prioridad' => 5,
            'acciones' => ['apelaciones.resolver'],
            'sujeto' => ['autenticado' => ['=' => true]],
            'objeto' => ['usuario_id' => ['=' => '@sujeto.id']],
            'entorno' => [],
            'decision' => 'denegar',
        ],

        // ------------------------------------------------------------------
        // 7. Certificados de divulgación (objeto: el informe)
        // ------------------------------------------------------------------
        // Solo existen para informes cerrados como resueltos: antes la vulnerabilidad no está
        // mitigada y un informe validado todavía puede acabar rechazado o duplicado.
        // El administrador entra por admin-bypass-total; el simulador no tiene más regla que esa.
        [
            'id' => 'inv-ver-certificado-propio',
            'descripcion' => 'Un investigador ve el certificado de sus informes cerrados.',
            'prioridad' => 20,
            'acciones' => ['certificados.ver'],
            'sujeto' => ['roles' => ['contains' => 'investigador']],
            'objeto' => [
                'investigador_id' => ['=' => '@sujeto.id'],
                'estado' => ['=' => 'cerrado'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'empresa-ver-certificados-de-sus-programas',
            'descripcion' => 'Una empresa ve los certificados de los informes cerrados de sus programas.',
            'prioridad' => 35,
            'acciones' => ['certificados.ver'],
            'sujeto' => ['roles' => ['contains' => 'empresa'], 'empresa_id' => ['is_not_null']],
            'objeto' => [
                'estado' => ['=' => 'cerrado'],
                'programa.empresa_id' => ['=' => '@sujeto.empresa_id'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
        [
            'id' => 'moderador-ver-certificados-de-sus-programas',
            'descripcion' => 'Un moderador ve los certificados de los informes cerrados de sus programas.',
            'prioridad' => 35,
            'acciones' => ['certificados.ver'],
            'sujeto' => ['roles' => ['contains' => 'moderador']],
            'objeto' => [
                'estado' => ['=' => 'cerrado'],
                'programa_id' => ['in' => '@sujeto.programas_moderados'],
            ],
            'entorno' => [],
            'decision' => 'permitir',
        ],
    ],

];
