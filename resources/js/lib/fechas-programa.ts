/** Mismo mínimo que valida el servidor (Programa::DURACION_MINIMA_DIAS). */
export const DURACION_MINIMA_DIAS = 3;

/** Suma días a una fecha yyyy-mm-dd sin pasar por zonas horarias. */
export function sumarDias(fecha: string, dias: number): string {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const d = new Date(Date.UTC(anio, mes - 1, dia + dias));

    return d.toISOString().slice(0, 10);
}

/**
 * Primera fecha de fin posible: el mínimo de días después del inicio (o de hoy si aún no
 * hay inicio). Sirve de `min` del campo de fecha; el servidor lo vuelve a comprobar.
 */
export function finMinimo(
    inicio: string | null | undefined,
    hoy: string,
): string {
    const desde = inicio && inicio > hoy ? inicio : hoy;

    return sumarDias(desde, DURACION_MINIMA_DIAS);
}
