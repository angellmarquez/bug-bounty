import type { FotoAdjunta } from '@/types/domain';

export function formatearTamano(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Errores de validación de fotos que devuelve Laravel (`fotos`, `fotos.0`, `fotos.1`...)
 * reunidos en un solo mensaje.
 */
export function errorDeFotos(
    errores: Record<string, string | undefined>,
): string {
    return Object.entries(errores)
        .filter(
            ([campo, mensaje]) =>
                (campo === 'fotos' || campo.startsWith('fotos.')) && mensaje,
        )
        .map(([, mensaje]) => mensaje)
        .join(' ');
}

export function huellaCorta(foto: Pick<FotoAdjunta, 'sha256'>): string {
    return `${foto.sha256.slice(0, 12)}…`;
}
