/**
 * Texto de un enlace de paginación de Laravel ("&laquo; Previous", "3", "Next &raquo;") sin
 * pasar por {@html}: se decodifican solo las entidades que usa el paginador y se muestra
 * como texto plano, así ninguna etiqueta puede inyectar HTML en la página.
 */
export function etiquetaPaginacion(etiqueta: string): string {
    return etiqueta
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&')
        .replace(/<[^>]*>/g, '')
        .trim();
}
