export type CategoriaAuditoria =
    | 'reportes'
    | 'programas'
    | 'empresas'
    | 'usuarios'
    | 'moderadores'
    | 'sanciones'
    | 'apelaciones'
    | 'config'
    | 'pgp'
    | 'seguridad'
    | 'otro';

/** Colores por categoría, mismo lenguaje visual que `lib/roles.ts` (tokens `chart-*`). */
export const CATEGORIAS: Record<
    CategoriaAuditoria,
    { etiqueta: string; clase: string }
> = {
    reportes: {
        etiqueta: 'Reportes',
        clase: 'border-chart-1/40 bg-chart-1/10 text-chart-1',
    },
    programas: {
        etiqueta: 'Programas',
        clase: 'border-chart-5/40 bg-chart-5/10 text-chart-5',
    },
    empresas: {
        etiqueta: 'Empresas',
        clase: 'border-chart-5/40 bg-chart-5/10 text-chart-5',
    },
    usuarios: {
        etiqueta: 'Usuarios',
        clase: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
    },
    moderadores: {
        etiqueta: 'Moderadores',
        clase: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
    },
    sanciones: {
        etiqueta: 'Sanciones',
        clase: 'border-chart-3/40 bg-chart-3/10 text-chart-3',
    },
    apelaciones: {
        etiqueta: 'Apelaciones',
        clase: 'border-chart-4/40 bg-chart-4/10 text-chart-4',
    },
    config: {
        etiqueta: 'Configuración',
        clase: 'border-chart-3/40 bg-chart-3/10 text-chart-3',
    },
    pgp: {
        etiqueta: 'PGP',
        clase: 'border-muted-foreground/30 bg-muted text-muted-foreground',
    },
    seguridad: {
        etiqueta: 'Seguridad',
        clase: 'border-destructive/40 bg-destructive/10 text-destructive',
    },
    otro: {
        etiqueta: 'Otro',
        clase: 'border-muted-foreground/30 bg-muted text-muted-foreground',
    },
};

const ETIQUETAS: Record<string, string> = {
    'reportes.creado': 'Creó un reporte',
    'reportes.editado': 'Editó un reporte',
    'reportes.enviado': 'Envió un reporte',
    'reportes.revision_iniciada': 'Inició la revisión de un reporte',
    'reportes.asignado': 'Asignó un reporte',
    'reportes.validado': 'Validó un reporte',
    'reportes.rechazado': 'Rechazó un reporte',
    'reportes.marcado_duplicado': 'Marcó un reporte como duplicado',
    'reportes.marcado_en_reparacion': 'Marcó un reporte en reparación',
    'reportes.cerrado': 'Cerró un reporte',
    'programas.creado': 'Creó un programa',
    'programas.editado': 'Editó un programa',
    'programas.estado_cambiado': 'Cambió el estado de un programa',
    'programas.eliminado': 'Eliminó un programa',
    'empresa.registrada': 'Registró su empresa',
    'empresa.invitacion.creada': 'Invitó a un investigador',
    'empresa.invitacion.cancelada': 'Canceló una invitación',
    'empresa.invitacion.aceptada': 'Aceptó una invitación',
    'empresa.invitacion.rechazada': 'Rechazó una invitación',
    'empresa.miembro.eliminado': 'Retiró a un miembro de la empresa',
    'admin.empresa.aprobada': 'Aprobó una empresa',
    'admin.empresa.rechazada': 'Rechazó una empresa',
    'admin.empresa.suspendida': 'Suspendió una empresa',
    'admin.empresa.reactivada': 'Reactivó una empresa',
    'admin.moderador.asignado': 'Asignó el rol de moderador',
    'admin.moderador.revocado': 'Revocó el rol de moderador',
    'admin.moderador.programa.asignado': 'Asignó un moderador a un programa',
    'admin.moderador.programa.revocado': 'Retiró un moderador de un programa',
    'admin.usuario.rol_cambiado': 'Cambió el rol de un usuario',
    'admin.config.reputacion_actualizada':
        'Actualizó la configuración de reputación',
    'sancion.aplicada': 'Aplicó una sanción',
    'sancion.revocada': 'Revocó una sanción',
    'apelacion.creada': 'Presentó una apelación',
    'apelacion.resuelta': 'Resolvió una apelación',
    'pgp.clave_generada': 'Se generó la clave PGP de la plataforma',
    'abac.denegado': 'Intento de acción denegado',
};

/** Etiqueta legible para una acción; si no está en el diccionario, humaniza el slug crudo. */
export function etiquetaAccion(accion: string): string {
    return ETIQUETAS[accion] ?? humanizar(accion);
}

function humanizar(accion: string): string {
    return accion
        .replace(/[._]/g, ' ')
        .replace(/\b\w/g, (letra) => letra.toUpperCase());
}

export function categoriaDe(accion: string): CategoriaAuditoria {
    if (accion.startsWith('reportes.')) return 'reportes';
    if (accion.startsWith('programas.')) return 'programas';
    if (accion.startsWith('empresa.') || accion.startsWith('admin.empresa.'))
        return 'empresas';
    if (accion.startsWith('admin.usuario')) return 'usuarios';
    if (accion.startsWith('admin.moderador')) return 'moderadores';
    if (accion.startsWith('sancion.')) return 'sanciones';
    if (accion.startsWith('apelacion.')) return 'apelaciones';
    if (accion.startsWith('admin.config')) return 'config';
    if (accion.startsWith('pgp.')) return 'pgp';
    if (accion.startsWith('abac.')) return 'seguridad';

    return 'otro';
}

export type CambioConfig = { ruta: string; antes: unknown; despues: unknown };

/**
 * Compara solo las claves presentes en `nueva` contra `anterior` (no al revés):
 * `config_nueva` de una actualización de reputación es un subconjunto editable
 * de `config_anterior` (que trae también rangos/acceso/cheat, que no se tocan
 * desde ese formulario), así que compararlas como objetos completos marcaría
 * esas claves como "eliminadas" sin que eso sea cierto.
 */
export function diferenciasDeConfig(
    anterior: unknown,
    nueva: unknown,
    prefijo = '',
): CambioConfig[] {
    if (typeof nueva !== 'object' || nueva === null || Array.isArray(nueva)) {
        return anterior === nueva
            ? []
            : [{ ruta: prefijo || 'valor', antes: anterior, despues: nueva }];
    }

    const anteriorObjeto =
        typeof anterior === 'object' &&
        anterior !== null &&
        !Array.isArray(anterior)
            ? (anterior as Record<string, unknown>)
            : {};

    return Object.entries(nueva as Record<string, unknown>).flatMap(
        ([clave, valor]) =>
            diferenciasDeConfig(
                anteriorObjeto[clave],
                valor,
                prefijo ? `${prefijo}.${clave}` : clave,
            ),
    );
}

/** Texto corto para mostrar un valor de config en un chip ("antes → después"). */
export function formatearValorConfig(valor: unknown): string {
    if (valor === null || valor === undefined) return '—';
    if (typeof valor === 'boolean') return valor ? 'sí' : 'no';
    if (typeof valor === 'string') return valor;
    if (typeof valor === 'number') return String(valor);

    return JSON.stringify(valor);
}
