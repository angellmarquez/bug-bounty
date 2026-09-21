export type Rango = {
    clave: string;
    nombre: string;
    minimo: number;
};

export type NivelAcceso = 'bajo' | 'medio' | 'alto';

export type NivelAccesoInfo = {
    valor: NivelAcceso;
    etiqueta: string;
    rango: string;
    rangoNombre: string;
    minimo: number;
};

export type ReputacionConfig = {
    rangos: Rango[];
    niveles: NivelAccesoInfo[];
};

export type RangoDetalle = Rango & {
    siguiente: Rango | null;
    faltan: number | null;
    progreso: number;
};

export type CuentaEstado = {
    roles: { slug: string; nombre: string }[];
    reputacion: number;
    rango: RangoDetalle;
    moderador: { programas: { id: number; nombre: string }[] } | null;
    suspension: {
        hasta: string | null;
        motivo: string;
        gravedad: string;
    } | null;
    empresa: {
        id: number;
        nombre: string;
        estado: string;
        motivo: string | null;
        rol_interno: string | null;
    } | null;
};

/**
 * Rango que corresponde a una cantidad de puntos. Espeja la lógica del servidor
 * (`App\Services\Reputacion\Rangos`); los umbrales llegan de `config/reputacion.php`.
 */
export function rangoDe(puntos: number, rangos: Rango[]): Rango {
    const ordenados = [...rangos].sort((a, b) => a.minimo - b.minimo);
    let actual = ordenados[0] ?? {
        clave: 'bronce',
        nombre: 'Bronce',
        minimo: 0,
    };

    for (const rango of ordenados) {
        if (puntos >= rango.minimo) actual = rango;
    }

    return actual;
}

const ESTILOS_RANGO: Record<string, string> = {
    bronce: 'border-amber-700/40 bg-amber-700/15 text-amber-800 dark:border-amber-600/40 dark:bg-amber-600/15 dark:text-amber-400',
    plata: 'border-slate-400/50 bg-slate-400/15 text-slate-700 dark:border-slate-300/40 dark:bg-slate-300/10 dark:text-slate-300',
    oro: 'border-yellow-500/50 bg-yellow-500/15 text-yellow-800 dark:border-yellow-400/40 dark:bg-yellow-400/15 dark:text-yellow-300',
    platino:
        'border-cyan-500/40 bg-cyan-500/15 text-cyan-800 dark:border-cyan-400/40 dark:bg-cyan-400/15 dark:text-cyan-300',
    diamante:
        'border-violet-500/40 bg-violet-500/15 text-violet-800 dark:border-violet-400/40 dark:bg-violet-400/15 dark:text-violet-300',
};

export function estiloRango(clave: string): string {
    return ESTILOS_RANGO[clave] ?? 'border-border bg-muted text-foreground';
}

const ESTILOS_ROL: Record<string, string> = {
    administrador:
        'border-rose-500/40 bg-rose-500/15 text-rose-800 dark:text-rose-300',
    moderador:
        'border-violet-500/40 bg-violet-500/15 text-violet-800 dark:text-violet-300',
    empresa: 'border-sky-500/40 bg-sky-500/15 text-sky-800 dark:text-sky-300',
    investigador:
        'border-emerald-500/40 bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
};

export function estiloRol(slug: string): string {
    return ESTILOS_ROL[slug] ?? 'border-border bg-muted text-foreground';
}

const ESTADOS_EMPRESA: Record<string, { etiqueta: string; estilo: string }> = {
    pendiente: {
        etiqueta: 'Pendiente de aprobación',
        estilo: 'border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-300',
    },
    aprobada: {
        etiqueta: 'Aprobada',
        estilo: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
    },
    suspendida: {
        etiqueta: 'Suspendida',
        estilo: 'border-rose-500/40 bg-rose-500/15 text-rose-800 dark:text-rose-300',
    },
    rechazada: {
        etiqueta: 'Rechazada',
        estilo: 'border-rose-500/40 bg-rose-500/15 text-rose-800 dark:text-rose-300',
    },
};

export function estadoEmpresa(estado: string): {
    etiqueta: string;
    estilo: string;
} {
    return (
        ESTADOS_EMPRESA[estado] ?? {
            etiqueta: estado,
            estilo: 'border-border bg-muted text-foreground',
        }
    );
}
