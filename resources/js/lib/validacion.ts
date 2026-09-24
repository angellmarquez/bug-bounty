/**
 * Formatos que el servidor exige (ver App\Concerns\ProfileValidationRules). Se repiten aquí
 * solo para avisar antes de enviar el formulario: la validación que cuenta es la del servidor.
 */

/** Nombre de persona: solo letras (con tildes y ñ) separadas por espacio, apóstrofo o guion. */
export const PATRON_NOMBRE = String.raw`\p{L}+(?:[ '\-]\p{L}+)*`;

export const AYUDA_NOMBRE =
    'Solo letras y espacios (se admiten tildes, ñ, apóstrofo o guion). Sin números.';
