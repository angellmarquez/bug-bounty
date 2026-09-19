import type {
    EstadoApelacion,
    EstadoClavePgp,
    EstadoPrograma,
    EstadoReporte,
    EstadoSancion,
    GravedadSancion,
    Severidad,
} from '@/types/enums';

const badgeClass =
    'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2';

export function estadoReporteColor(estado: EstadoReporte): string {
    const map: Record<EstadoReporte, string> = {
        borrador: `${badgeClass} border-secondary bg-secondary text-secondary-foreground`,
        enviado: `${badgeClass} border-secondary bg-secondary text-secondary-foreground`,
        en_revision: `${badgeClass} border-transparent bg-chart-2 text-white`,
        duplicado: `${badgeClass} border-transparent bg-chart-4 text-white`,
        fuera_de_alcance: `${badgeClass} border-transparent bg-chart-4 text-white`,
        validado: `${badgeClass} border-transparent bg-chart-2 text-white`,
        en_reparacion: `${badgeClass} border-transparent bg-chart-2 text-white`,
        pagado: `${badgeClass} border-transparent bg-chart-1 text-white`,
        cerrado: `${badgeClass} border-transparent bg-muted text-muted-foreground`,
        pago_pendiente: `${badgeClass} border-transparent bg-chart-5 text-white`,
        rechazado: `${badgeClass} border-transparent bg-chart-4 text-white`,
    };
    return map[estado] ?? badgeClass;
}

export function severidadColor(severidad: Severidad): string {
    const map: Record<Severidad, string> = {
        critica: `${badgeClass} border-transparent bg-chart-3 text-white`,
        alta: `${badgeClass} border-transparent bg-chart-4 text-white`,
        media: `${badgeClass} border-transparent bg-chart-2 text-white`,
        baja: `${badgeClass} border-transparent bg-chart-1 text-white`,
        ninguna: `${badgeClass} border-transparent bg-muted text-muted-foreground`,
    };
    return map[severidad] ?? badgeClass;
}

export function gravedadSancionColor(gravedad: GravedadSancion): string {
    const map: Record<GravedadSancion, string> = {
        leve: `${badgeClass} border-secondary bg-secondary text-secondary-foreground`,
        media: `${badgeClass} border-transparent bg-chart-4 text-white`,
        grave: `${badgeClass} border-transparent bg-chart-3 text-white`,
    };
    return map[gravedad] ?? badgeClass;
}

export function estadoProgramaColor(estado: EstadoPrograma): string {
    const map: Record<EstadoPrograma, string> = {
        activo: `${badgeClass} border-transparent bg-chart-1 text-white`,
        en_pausa: `${badgeClass} border-transparent bg-chart-4 text-white`,
        archivado: `${badgeClass} border-transparent bg-muted text-muted-foreground`,
        borrador: `${badgeClass} border-secondary bg-secondary text-secondary-foreground`,
    };
    return map[estado] ?? badgeClass;
}

export function estadoSancionColor(estado: EstadoSancion): string {
    const map: Record<EstadoSancion, string> = {
        aplicada: `${badgeClass} border-transparent bg-chart-3 text-white`,
        apelada: `${badgeClass} border-transparent bg-chart-5 text-white`,
        revocada: `${badgeClass} border-transparent bg-chart-1 text-white`,
    };
    return map[estado] ?? badgeClass;
}

export function estadoApelacionColor(estado: EstadoApelacion): string {
    const map: Record<EstadoApelacion, string> = {
        pendiente: `${badgeClass} border-transparent bg-chart-4 text-white`,
        aprobada: `${badgeClass} border-transparent bg-chart-1 text-white`,
        rechazada: `${badgeClass} border-transparent bg-chart-3 text-white`,
    };
    return map[estado] ?? badgeClass;
}

export function estadoClavePgpColor(estado: EstadoClavePgp): string {
    const map: Record<EstadoClavePgp, string> = {
        pendiente_verificacion: `${badgeClass} border-transparent bg-chart-4 text-white`,
        activa: `${badgeClass} border-transparent bg-chart-1 text-white`,
        expirada: `${badgeClass} border-transparent bg-muted text-muted-foreground`,
        revocada: `${badgeClass} border-transparent bg-chart-3 text-white`,
    };
    return map[estado] ?? badgeClass;
}

export function estadoReporteLabel(estado: EstadoReporte): string {
    const map: Record<EstadoReporte, string> = {
        borrador: 'Borrador',
        enviado: 'Enviado',
        en_revision: 'En revisión',
        duplicado: 'Duplicado',
        fuera_de_alcance: 'Fuera de alcance',
        validado: 'Validado',
        en_reparacion: 'En reparación',
        pagado: 'Pagado',
        cerrado: 'Cerrado',
        pago_pendiente: 'Pago pendiente',
        rechazado: 'Rechazado',
    };
    return map[estado] ?? estado;
}

export function severidadLabel(severidad: Severidad): string {
    const map: Record<Severidad, string> = {
        critica: 'Crítica',
        alta: 'Alta',
        media: 'Media',
        baja: 'Baja',
        ninguna: 'Ninguna',
    };
    return map[severidad] ?? severidad;
}

export function gravedadSancionLabel(gravedad: GravedadSancion): string {
    const map: Record<GravedadSancion, string> = {
        leve: 'Leve',
        media: 'Media',
        grave: 'Grave',
    };
    return map[gravedad] ?? gravedad;
}

export function estadoProgramaLabel(estado: EstadoPrograma): string {
    const map: Record<EstadoPrograma, string> = {
        borrador: 'Borrador',
        activo: 'Activo',
        en_pausa: 'En pausa',
        archivado: 'Archivado',
    };
    return map[estado] ?? estado;
}

export function estadoSancionLabel(estado: EstadoSancion): string {
    const map: Record<EstadoSancion, string> = {
        aplicada: 'Aplicada',
        apelada: 'Apelada',
        revocada: 'Revocada',
    };
    return map[estado] ?? estado;
}

export function estadoApelacionLabel(estado: EstadoApelacion): string {
    const map: Record<EstadoApelacion, string> = {
        pendiente: 'Pendiente',
        aprobada: 'Aprobada',
        rechazada: 'Rechazada',
    };
    return map[estado] ?? estado;
}

export function estadoClavePgpLabel(estado: EstadoClavePgp): string {
    const map: Record<EstadoClavePgp, string> = {
        pendiente_verificacion: 'Pendiente verificación',
        activa: 'Activa',
        expirada: 'Expirada',
        revocada: 'Revocada',
    };
    return map[estado] ?? estado;
}
