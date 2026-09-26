import type { User } from '@/types/auth';
import type {
    EstadoApelacion,
    EstadoClavePgp,
    EstadoPrograma,
    EstadoReporte,
    EstadoSancion,
    GravedadSancion,
    Severidad,
    TipoEventoReporte,
    TipoObjetivo,
} from '@/types/enums';

import type { NivelAcceso } from '@/lib/rangos';

export type Rol = {
    id: number;
    nombre: string;
    slug: string;
    descripcion: string | null;
    created_at: string;
    updated_at: string;
};

export type Empresa = {
    id: number;
    razon_social: string;
    nombre_comercial: string | null;
    identificador_fiscal: string;
    slug: string;
    email: string;
    telefono: string | null;
    sitio_web: string | null;
    estado: 'pendiente' | 'aprobada' | 'rechazada' | 'suspendida';
    motivo_estado: string | null;
    aprobado_por: number | null;
    aprobado_en: string | null;
    created_at: string;
    updated_at: string;
    usuarios?: User[];
};

export type ObjetivoPrograma = {
    id: number;
    programa_id: number;
    tipo: TipoObjetivo;
    valor: string;
    descripcion: string | null;
    created_at: string;
    updated_at: string;
};

export type PocSchemaFieldType =
    | 'text'
    | 'textarea'
    | 'select'
    | 'number'
    | 'url'
    | 'code';

export type PocSchemaField = {
    name: string;
    label: string;
    type: PocSchemaFieldType;
    required?: boolean;
    placeholder?: string;
    help?: string;
    options?: { value: string; label: string }[];
    repeatable?: boolean;
    defaultValue?: string;
};

export type Programa = {
    id: number;
    nombre: string;
    slug: string;
    descripcion: string;
    bugs_buscados: string | null;
    estado: EstadoPrograma;
    es_publico: boolean;
    nivel_acceso: NivelAcceso;
    poc_schema: PocSchemaField[] | null;
    creado_por: number | null;
    inicia_en: string | null;
    termina_en: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    creador?: User;
    objetivos?: ObjetivoPrograma[];
    reportes_count?: number;
    empresa?: {
        id: number;
        razon_social: string;
        nombre_comercial: string | null;
    } | null;
};

export type Reporte = {
    id: number;
    numero_reporte: string;
    programa_id: number;
    investigador_id: number;
    asignado_a: number | null;
    titulo: string;
    descripcion: string;
    categoria: string | null;
    vector_cvss: string | null;
    puntuacion_cvss: number | null;
    severidad: Severidad | null;
    poc: Record<string, unknown> | null;
    estado: EstadoReporte;
    es_duplicado_de: number | null;
    notas_internas: string | null;
    enviado_en: string | null;
    cerrado_en: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    programa?: Programa;
    investigador?: User;
    asignadoA?: User | null;
    eventos?: EventoReporte[];
    duplicadoDe?: Reporte | null;
};

export type EventoReporte = {
    id: number;
    reporte_id: number;
    tipo: TipoEventoReporte;
    actor_id: number | null;
    descripcion: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    actor?: User | null;
};

/**
 * Informe en formato de lista: sin descripción ni PoC, con el investigador y su
 * reputación. El contenido completo se lee en la página del informe.
 */
export type ReporteCompacto = {
    id: number;
    numero_reporte: string;
    titulo: string;
    estado: EstadoReporte;
    severidad: Severidad | null;
    programa_nombre?: string;
    enviado_en: string | null;
    es_duplicado_de?: number | null;
    asignado_a?: { id: number; name: string } | null;
    investigador: {
        id: number;
        name: string;
        reputation_score: number;
        reportes_descartados?: number;
    };
};

export type ClavePgpResumen = {
    id: number;
    huella: string;
    algoritmo: string | null;
    bits: number | null;
    es_principal: boolean;
};

export type ClavePgp = {
    id: number;
    usuario_id: number;
    id_clave: string;
    huella: string;
    clave_publica: string;
    algoritmo: string | null;
    bits: number | null;
    creada_en: string | null;
    expira_en: string | null;
    estado: EstadoClavePgp;
    es_principal: boolean;
    verificada_en: string | null;
    ultimo_uso_en: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    usuario?: User;
};

export type Sancion = {
    id: number;
    usuario_id: number;
    reporte_id: number | null;
    motivo: string;
    gravedad: GravedadSancion;
    puntos: number;
    estado: EstadoSancion;
    suspension_desde: string | null;
    suspension_hasta: string | null;
    plazo_apelacion: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    usuario?: User;
    reporte?: Reporte | null;
    // Lo calcula el servidor: vigente, dentro de plazo y sin apelación previa.
    puede_apelar?: boolean;
};

/** Un aviso de la campana (notificación interna). */
export type Notificacion = {
    id: string;
    tipo:
        | 'informe'
        | 'sancion'
        | 'apelacion'
        | 'empresa'
        | 'moderacion'
        | 'reputacion'
        | 'invitacion'
        // Admite tipos nuevos sin perder el autocompletado de los conocidos.
        | (string & {});
    titulo: string;
    mensaje: string;
    url: string | null;
    leida: boolean;
    created_at: string | null;
};

export type ResumenNotificaciones = {
    no_leidas: number;
    recientes: Notificacion[];
};

/** Un paso del registro de control de una apelación (con su huella SHA-256 encadenada). */
export type PasoApelacion = {
    id: number;
    tipo: 'presentada' | 'aprobada' | 'rechazada' | (string & {});
    actor?: { id: number; name: string | null } | null;
    actor_rol: string | null;
    ip?: string | null;
    user_agent?: string | null;
    nota: string | null;
    datos?: Record<string, unknown> | null;
    huella: string;
    huella_anterior?: string | null;
    created_at: string | null;
};

/** Apelación tal como la ve quien la resuelve (moderador o administrador). */
export type ApelacionParaResolver = {
    id: number;
    estado: EstadoApelacion;
    motivo: string;
    nota_resolucion: string | null;
    created_at: string;
    resuelta_en: string | null;
    usuario: { id: number; name: string } | null;
    resuelta_por: { id: number; name: string } | null;
    sancion: {
        id: number;
        motivo: string;
        gravedad: GravedadSancion;
        puntos: number;
        estado: EstadoSancion;
        plazo_apelacion: string | null;
        origen: 'triaje' | 'auditoria';
        aplicada_por: { id: number; name: string } | null;
        reporte: { id: number; numero_reporte: string; titulo: string } | null;
    };
    puede_resolver: boolean;
    motivo_bloqueo: string | null;
};

export type Apelacion = {
    id: number;
    sancion_id: number;
    usuario_id: number;
    motivo: string;
    estado: EstadoApelacion;
    resuelta_por: number | null;
    nota_resolucion: string | null;
    resuelta_en: string | null;
    created_at: string;
    updated_at: string;
    sancion?: Sancion;
    usuario?: User;
    resueltaPor?: User | null;
};

export type EntradaReputacion = {
    id: number;
    usuario_id: number;
    reporte_id: number | null;
    sancion_id: number | null;
    apelacion_id: number | null;
    puntos: number;
    motivo: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
    usuario?: User;
    reporte?: Reporte | null;
    sancion?: Sancion | null;
    apelacion?: Apelacion | null;
};

export type Auditoria = {
    id: number;
    accion: string;
    entidad_type: string | null;
    entidad_id: number | null;
    detalle: Record<string, unknown> | null;
    ip: string | null;
    created_at: string;
    usuario: { id: number; name: string; roles: string[] } | null;
};

export type DashboardStats = {
    reportes_total: number;
    reportes_abiertos: number;
    reportes_cerrados: number;
    reportes_borrador: number;
    programas_activos: number;
    reputacion: number;
};

export type DashboardRoleStatsEmpresa = {
    tipo: 'empresa';
    estado: string | undefined;
    programas_total: number;
    programas_activos: number;
    reportes_recibidos: number;
};

export type DashboardRoleStatsModerador = {
    tipo: 'moderador';
    pendientes_revision: number;
    por_revisar: number;
    validados: number;
    rechazados: number;
    sanciones_aplicadas: number;
};

export type DashboardRoleStatsAdmin = {
    tipo: 'administrador';
    empresas_pendientes: number;
    empresas_aprobadas: number;
    moderadores: number;
    sanciones_activas: number;
};

/** Mapa acumulativo: un usuario con múltiples roles recibe todas sus métricas sin colisión. */
export type DashboardRoleStats = {
    empresa?: DashboardRoleStatsEmpresa;
    moderador?: DashboardRoleStatsModerador;
    administrador?: DashboardRoleStatsAdmin;
};

export type SancionDashboard = {
    id: number;
    motivo: string;
    gravedad: GravedadSancion;
    puntos: number;
    estado: EstadoSancion;
    suspension_desde: string | null;
    suspension_hasta: string | null;
    plazo_apelacion: string | null;
    en_plazo: boolean;
    puede_apelar: boolean;
    comentario_moderador?: string | null;
    moderador?: { id: number; name: string } | null;
    reporte?: { id: number; numero_reporte: string; titulo: string } | null;
    apelacion?: { id: number; motivo: string; estado: string } | null;
};

export type GlobalLeaderboardStats = {
    total_investigadores: number;
    total_vulnerabilidades_resueltas: number;
    puntos_totales_repartidos: number;
};

export type HallOfFameRankingItem = {
    posicion: number;
    id: number;
    name: string;
    puntos: number;
    rango: {
        clave: string;
        nombre: string;
        minimo: number;
    };
    reportes_resueltos: number;
    severidades: {
        critica: number;
        alta: number;
        media: number;
        baja: number;
    };
};

/** Foto de evidencia (informe o apelación). Se guarda cifrada; `url` la sirve descifrada tras ABAC. */
export type FotoAdjunta = {
    id: number;
    nombre: string;
    mime: string;
    tamano: number;
    ancho: number;
    alto: number;
    sha256: string;
    url: string;
    created_at: string | null;
};

export type LimitesFotos = {
    max: number;
    max_kb: number;
    max_total_kb: number;
    mimes: string[];
};
