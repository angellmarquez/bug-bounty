export type RolSlug =
    | 'administrador'
    | 'moderador'
    | 'empresa'
    | 'investigador';

/**
 * Metadata visual única para cada rol: la usan `RolesUsuario.svelte` y la
 * página de Auditoría, para que un mismo rol se vea igual en toda la app.
 */
export const ROLES: Record<
    RolSlug,
    { etiqueta: string; descripcion: string; clase: string }
> = {
    administrador: {
        etiqueta: 'Administrador',
        descripcion:
            'Gestionas la plataforma: empresas, moderadores, usuarios y configuración.',
        clase: 'border-chart-3/40 bg-chart-3/10 text-chart-3',
    },
    moderador: {
        etiqueta: 'Moderador',
        descripcion:
            'Revisas los informes enviados a los programas: validas, rechazas (y penalizas reportes falsos) o marcas duplicados desde Moderación.',
        clase: 'border-chart-2/40 bg-chart-2/10 text-chart-2',
    },
    empresa: {
        etiqueta: 'Empresa',
        descripcion:
            'Publicas y gestionas los programas de tu empresa y lees los informes que recibe.',
        clase: 'border-chart-5/40 bg-chart-5/10 text-chart-5',
    },
    investigador: {
        etiqueta: 'Investigador',
        descripcion:
            'Buscas vulnerabilidades en los programas publicados y envías informes.',
        clase: 'border-chart-1/40 bg-chart-1/10 text-chart-1',
    },
};

/** Un usuario puede tener varios roles: este es el orden en que se prioriza cuál mostrar. */
export const ORDEN_ROLES: RolSlug[] = [
    'administrador',
    'moderador',
    'empresa',
    'investigador',
];

/** El rol "principal" a mostrar cuando solo hay espacio para uno (ej. un badge compacto). */
export function rolPrincipal(
    roles: string[] | null | undefined,
): RolSlug | null {
    if (!roles || roles.length === 0) return null;

    return ORDEN_ROLES.find((rol) => roles.includes(rol)) ?? null;
}
