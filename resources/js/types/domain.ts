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
    estado: EstadoPrograma;
    moneda: string;
    recompensa_min: number;
    recompensa_max: number;
    requiere_poc: boolean;
    es_publico: boolean;
    reputacion_minima: number;
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
    recompensa: number | null;
    moneda: string;
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
};

export type Apelacion = {
    id: number;
    sancion_id: number;
    usuario_id: number;
    motivo: string;
    estado: EstadoApelacion;
    resuelta_por: number | null;
    resolucion: string | null;
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
    saldo: number;
    descripcion: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
    usuario?: User;
    reporte?: Reporte | null;
    sancion?: Sancion | null;
    apelacion?: Apelacion | null;
};

export type Auditoria = {
    id: number;
    usuario_id: number | null;
    accion: string;
    entidad_type: string;
    entidad_id: number;
    metadata: Record<string, unknown> | null;
    created_at: string;
    usuario?: User | null;
};

export type DashboardStats = {
    reportes_total: number;
    reportes_abiertos: number;
    reportes_cerrados: number;
    reportes_borrador: number;
    programas_activos: number;
    reputacion: number;
};

export type DashboardRoleStats =
    | {
          tipo: 'empresa';
          estado: string | undefined;
          programas_total: number;
          programas_activos: number;
          miembros: number;
          reportes_recibidos: number;
      }
    | {
          tipo: 'moderador';
          pendientes_revision: number;
          validados: number;
          rechazados: number;
          sanciones_aplicadas: number;
      }
    | {
          tipo: 'administrador';
          empresas_pendientes: number;
          empresas_aprobadas: number;
          moderadores: number;
          sanciones_activas: number;
      }
    | Record<string, never>;
