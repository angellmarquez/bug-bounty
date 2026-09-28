// El wizard de informes solo monta el paso actual, pero los botones de guardar están en el
// último. Si el servidor rechaza un campo de un paso anterior, su mensaje quedaba en un paso
// oculto: el botón dejaba de girar y parecía que el informe no se enviaba. Con esto se lleva
// al usuario al paso del primer error y se le muestra un resumen visible en cualquier paso.

// Errores que la página ya muestra aparte, con su propio aviso.
const CON_AVISO_PROPIO = new Set(['pgp', 'limite']);

/** Paso del wizard (1-4) donde se corrige un campo. */
export function pasoDeCampo(campo: string): number {
    if (['programa_id', 'titulo', 'descripcion', 'categoria'].includes(campo)) {
        return 1;
    }

    if (['vector_cvss', 'puntuacion_cvss', 'severidad'].includes(campo)) {
        return 2;
    }

    if (
        campo === 'poc' ||
        campo.startsWith('poc.') ||
        campo === 'fotos' ||
        campo.startsWith('fotos.')
    ) {
        return 3;
    }

    return 4;
}

/** Mensajes del servidor que no tienen un aviso propio en la página. */
export function erroresDelServidor(
    errores: Record<string, string | undefined>,
): string[] {
    return Object.entries(errores)
        .filter(
            ([campo, mensaje]) =>
                !CON_AVISO_PROPIO.has(campo) &&
                typeof mensaje === 'string' &&
                mensaje !== '',
        )
        .map(([, mensaje]) => mensaje as string);
}

/** Primer paso con errores, o null si ninguno pertenece a un paso anterior al último. */
export function primerPasoConError(
    errores: Record<string, string | undefined>,
    ultimoPaso: number,
): number | null {
    const pasos = Object.keys(errores)
        .filter((campo) => !CON_AVISO_PROPIO.has(campo))
        .map(pasoDeCampo)
        .filter((paso) => paso < ultimoPaso);

    return pasos.length === 0 ? null : Math.min(...pasos);
}
