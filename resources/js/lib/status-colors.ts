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
    'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2';

/**
 * Paleta de "pills" suaves: fondo tenue + texto e icono saturados en el
 * mismo tono, con variante oscura a juego. Pensada para que cada estado se
 * distinga de un vistazo sin recurrir a bloques solidos de color.
 */
type TonoColor =
    | 'slate'
    | 'blue'
    | 'amber'
    | 'cyan'
    | 'indigo'
    | 'orange'
    | 'emerald'
    | 'red'
    | 'purple'
    | 'neutral'
    | 'zinc';

function pill(tono: TonoColor): string {
    const map: Record<TonoColor, string> = {
        slate: 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-300',
        blue: 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400',
        amber: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        cyan: 'border-cyan-500/20 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        indigo: 'border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        orange: 'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400',
        emerald:
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        red: 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
        purple: 'border-purple-500/20 bg-purple-500/10 text-purple-600 dark:text-purple-400',
        neutral:
            'border-neutral-500/20 bg-neutral-500/10 text-neutral-600 dark:text-neutral-400',
        zinc: 'border-zinc-500/20 bg-zinc-500/10 text-zinc-600 dark:text-zinc-400',
    };
    return `${badgeClass} ${map[tono]}`;
}

/**
 * Mismo tono por estado que usan los puntos de la linea de tiempo
 * ({@link estadoReporteDotColor}), para que badge y punto se lean como el
 * mismo lenguaje visual en toda la app.
 */
export function estadoReporteColor(estado: EstadoReporte): string {
    const map: Record<EstadoReporte, string> = {
        borrador: pill('slate'),
        enviado: pill('blue'),
        en_revision: pill('amber'),
        validado: pill('cyan'),
        en_reparacion: pill('indigo'),
        pago_pendiente: pill('orange'),
        pagado: pill('emerald'),
        rechazado: pill('red'),
        duplicado: pill('purple'),
        fuera_de_alcance: pill('neutral'),
        cerrado: pill('zinc'),
    };
    return map[estado] ?? pill('slate');
}

export function severidadColor(severidad: Severidad): string {
    const map: Record<Severidad, string> = {
        ninguna: pill('slate'),
        baja: pill('emerald'),
        media: pill('amber'),
        alta: pill('orange'),
        critica: pill('red'),
    };
    return map[severidad] ?? pill('slate');
}

export function gravedadSancionColor(gravedad: GravedadSancion): string {
    const map: Record<GravedadSancion, string> = {
        leve: pill('amber'),
        media: pill('orange'),
        grave: pill('red'),
    };
    return map[gravedad] ?? pill('slate');
}

export function estadoProgramaColor(estado: EstadoPrograma): string {
    const map: Record<EstadoPrograma, string> = {
        borrador: pill('slate'),
        activo: pill('emerald'),
        en_pausa: pill('amber'),
        archivado: pill('zinc'),
    };
    return map[estado] ?? pill('slate');
}

export function estadoSancionColor(estado: EstadoSancion): string {
    const map: Record<EstadoSancion, string> = {
        aplicada: pill('red'),
        apelada: pill('amber'),
        revocada: pill('emerald'),
    };
    return map[estado] ?? pill('slate');
}

export function estadoApelacionColor(estado: EstadoApelacion): string {
    const map: Record<EstadoApelacion, string> = {
        pendiente: pill('amber'),
        aprobada: pill('emerald'),
        rechazada: pill('red'),
    };
    return map[estado] ?? pill('slate');
}

export function estadoClavePgpColor(estado: EstadoClavePgp): string {
    const map: Record<EstadoClavePgp, string> = {
        pendiente_verificacion: pill('amber'),
        activa: pill('emerald'),
        expirada: pill('zinc'),
        revocada: pill('red'),
    };
    return map[estado] ?? pill('slate');
}

/**
 * Color sólido por estado de reporte, pensado para puntos/marcadores
 * (líneas de tiempo, leyendas) en vez de badges con texto.
 */
export function estadoReporteDotColor(estado: EstadoReporte): string {
    const map: Record<EstadoReporte, string> = {
        borrador: 'bg-slate-400',
        enviado: 'bg-blue-500',
        en_revision: 'bg-amber-500',
        validado: 'bg-cyan-500',
        en_reparacion: 'bg-indigo-500',
        pago_pendiente: 'bg-orange-500',
        pagado: 'bg-emerald-500',
        rechazado: 'bg-red-500',
        duplicado: 'bg-purple-500',
        fuera_de_alcance: 'bg-neutral-500',
        cerrado: 'bg-zinc-600',
    };
    return map[estado] ?? 'bg-muted-foreground';
}

/**
 * Estados que aun estan en ciclo de vida activo (no son un desenlace final).
 * Se usan para animar el punto en la linea de tiempo (pulso "en progreso").
 */
export function estadoReporteEnProgreso(estado: EstadoReporte): boolean {
    const abiertos: EstadoReporte[] = [
        'borrador',
        'enviado',
        'en_revision',
        'validado',
        'en_reparacion',
        'pago_pendiente',
    ];
    return abiertos.includes(estado);
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
