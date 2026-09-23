export const EstadoReporte = {
    Borrador: 'borrador',
    Enviado: 'enviado',
    EnRevision: 'en_revision',
    NeedsInfo: 'needs_info',
    Duplicado: 'duplicado',
    FueraDeAlcance: 'fuera_de_alcance',
    Validado: 'validado',
    EnReparacion: 'en_reparacion',
    Rechazado: 'rechazado',
    Cerrado: 'cerrado',
} as const;
export type EstadoReporte = (typeof EstadoReporte)[keyof typeof EstadoReporte];

export const EstadoPrograma = {
    Borrador: 'borrador',
    Activo: 'activo',
    EnPausa: 'en_pausa',
    Archivado: 'archivado',
} as const;
export type EstadoPrograma =
    (typeof EstadoPrograma)[keyof typeof EstadoPrograma];

export const Severidad = {
    Ninguna: 'ninguna',
    Baja: 'baja',
    Media: 'media',
    Alta: 'alta',
    Critica: 'critica',
} as const;
export type Severidad = (typeof Severidad)[keyof typeof Severidad];

export const TipoObjetivo = {
    Web: 'web',
    Api: 'api',
    Movil: 'movil',
    Otro: 'otro',
} as const;
export type TipoObjetivo = (typeof TipoObjetivo)[keyof typeof TipoObjetivo];

export const TipoEventoReporte = {
    Creado: 'creado',
    Enviado: 'enviado',
    CambioDeEstado: 'cambio_estado',
    Comentario: 'comentario',
    MarcadoDuplicado: 'marcado_duplicado',
    Sancion: 'sancion',
    Asignacion: 'asignacion',
} as const;
export type TipoEventoReporte =
    (typeof TipoEventoReporte)[keyof typeof TipoEventoReporte];

export const GravedadSancion = {
    Leve: 'leve',
    Media: 'media',
    Grave: 'grave',
} as const;
export type GravedadSancion =
    (typeof GravedadSancion)[keyof typeof GravedadSancion];

export const EstadoSancion = {
    Aplicada: 'aplicada',
    Apelada: 'apelada',
    Revocada: 'revocada',
} as const;
export type EstadoSancion = (typeof EstadoSancion)[keyof typeof EstadoSancion];

export const EstadoApelacion = {
    Pendiente: 'pendiente',
    Aprobada: 'aprobada',
    Rechazada: 'rechazada',
} as const;
export type EstadoApelacion =
    (typeof EstadoApelacion)[keyof typeof EstadoApelacion];

export const EstadoClavePgp = {
    PendienteVerificacion: 'pendiente_verificacion',
    Activa: 'activa',
    Expirada: 'expirada',
    Revocada: 'revocada',
} as const;
export type EstadoClavePgp =
    (typeof EstadoClavePgp)[keyof typeof EstadoClavePgp];

export const RolSlug = {
    Administrador: 'administrador',
    Moderador: 'moderador',
    Empresa: 'empresa',
    Investigador: 'investigador',
} as const;
export type RolSlug = (typeof RolSlug)[keyof typeof RolSlug];
