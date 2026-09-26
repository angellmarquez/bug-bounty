import { expect, test } from '@playwright/test';
import {
    entradasDelMenu,
    iniciarSesion,
    vigilarErrores,
    type Rol,
} from './helpers';

/** Entradas que el menú lateral debe mostrar a cada rol, en CUALQUIER página. */
const menuEsperado: Record<Rol, string[]> = {
    // El admin gestiona usuarios, configuración y auditoría: no participa en el día a día.
    admin: [
        'Dashboard',
        'Empresas',
        'Usuarios',
        'Moderadores',
        'Apelaciones',
        'Sanciones',
        'Auditoría',
    ],
    moderador: ['Dashboard', 'Moderación', 'Apelaciones', 'Todos los reportes'],
    investigador: ['Dashboard', 'Mis Reportes', 'Programas', 'Mi reputación'],
    sancionado: ['Dashboard', 'Mis Reportes', 'Programas', 'Mi reputación'],
    invitado: ['Dashboard', 'Mis Reportes', 'Programas', 'Mi reputación'],
    empresa: ['Dashboard', 'Reportes recibidos', 'Panel empresa'],
    doble: [
        'Dashboard',
        'Moderación',
        'Apelaciones',
        'Todos los reportes',
        'Programas',
    ],
};

/** Páginas que cada rol debe poder abrir (ids de los datos de ejemplo de seed.php). */
const recorrido: Record<Rol, string[]> = {
    admin: [
        '/dashboard',
        '/reportes',
        '/reportes/1',
        '/programas',
        '/programas/1',
        '/gestion/programas',
        '/admin/empresas',
        '/admin/usuarios',
        '/admin/moderadores',
        '/admin/sanciones',
        '/moderacion/apelaciones',
        '/admin/auditoria',
        '/admin/config/reputacion',
        '/admin/pgp',
    ],
    moderador: [
        '/dashboard',
        '/moderacion',
        '/moderacion/programas/1',
        '/moderacion/apelaciones',
        '/reportes',
        '/reportes/1',
        '/programas/1',
    ],
    invitado: [
        '/dashboard',
        '/reportes',
        '/programas',
        '/invitaciones',
        '/reputacion',
    ],
    sancionado: [
        '/dashboard',
        '/reputacion',
        '/reputacion/sanciones',
        '/reputacion/apelaciones',
        '/programas',
    ],
    investigador: [
        '/dashboard',
        '/reportes',
        '/reportes/1',
        '/reportes/3',
        '/reportes/3/editar',
        '/reportes/crear?programa=1',
        '/programas',
        '/programas/1',
        '/reputacion',
        '/reputacion/apelaciones',
    ],
    empresa: [
        '/dashboard',
        '/empresa',
        '/empresa/reportes',
        // La empresa solo abre informes ya triados: el 2 está validado (el 1 sigue en pre-triaje).
        '/reportes/2',
        '/programas/1',
        '/gestion/programas',
        '/gestion/programas/crear',
        '/gestion/programas/1/editar',
    ],
    doble: [
        '/dashboard',
        '/moderacion',
        '/moderacion/apelaciones',
        '/reportes',
        '/programas',
        '/programas/1',
        '/reportes/1',
    ],
};

const roles = Object.keys(menuEsperado) as Rol[];

for (const rol of roles) {
    test.describe(`rol ${rol}`, () => {
        test('cada página abre bien, con su menú completo y sin errores de JavaScript', async ({
            page,
        }) => {
            const errores = vigilarErrores(page);
            await iniciarSesion(page, rol);

            const problemas: string[] = [];

            for (const url of recorrido[rol]) {
                const respuesta = await page.goto(url);
                const estado = respuesta?.status() ?? 0;
                if (estado >= 400) {
                    problemas.push(`${url} -> HTTP ${estado}`);
                    continue;
                }

                await page
                    .locator('[data-slot="sidebar"]')
                    .first()
                    .waitFor({ state: 'visible', timeout: 8_000 })
                    .catch(() => {
                        problemas.push(`${url} -> no se ve el menú lateral`);
                    });

                const menu = (await entradasDelMenu(page))
                    .join(' | ')
                    .toLowerCase();
                for (const entrada of menuEsperado[rol]) {
                    if (!menu.includes(entrada.toLowerCase())) {
                        problemas.push(
                            `${url} -> falta "${entrada}" en el menú (hay: ${menu})`,
                        );
                    }
                }
            }

            expect(problemas, problemas.join('\n')).toEqual([]);
            expect(errores, errores.join('\n')).toEqual([]);
        });

        test('el menú no pierde entradas al navegar con clics (sin recargar)', async ({
            page,
        }) => {
            const errores = vigilarErrores(page);
            await iniciarSesion(page, rol);
            await page.goto('/dashboard');

            await expect(
                page.locator('[data-slot="sidebar"] a').first(),
            ).toBeVisible();

            // Los href del menú son URL absolutas: se comparan por ruta.
            const destinos = await page
                .locator('[data-slot="sidebar"] a')
                .evaluateAll((enlaces) => [
                    ...new Set(
                        enlaces
                            .map(
                                (enlace) =>
                                    new URL((enlace as HTMLAnchorElement).href),
                            )
                            .filter(
                                (url) => url.pathname !== '/dashboard' || true,
                            )
                            .map((url) => url.pathname + url.search),
                    ),
                ]);
            expect(
                destinos.length,
                'el menú no tiene enlaces que recorrer',
            ).toBeGreaterThan(1);

            const problemas: string[] = [];
            for (const destino of destinos) {
                await page
                    .locator(`[data-slot="sidebar"] a[href$="${destino}"]`)
                    .first()
                    .click();
                await page
                    .waitForURL(
                        (url) => url.pathname === destino.split('?')[0],
                        { timeout: 10_000 },
                    )
                    .catch(() => {
                        problemas.push(
                            `${destino} -> el clic no llevó a la página`,
                        );
                    });
                await page
                    .waitForLoadState('networkidle')
                    .catch(() => undefined);

                const menu = (await entradasDelMenu(page))
                    .join(' | ')
                    .toLowerCase();
                for (const entrada of menuEsperado[rol]) {
                    if (!menu.includes(entrada.toLowerCase())) {
                        problemas.push(
                            `tras ir a ${destino} falta "${entrada}" en el menú (hay: ${menu})`,
                        );
                    }
                }
            }

            expect(problemas, problemas.join('\n')).toEqual([]);
            expect(errores, errores.join('\n')).toEqual([]);
        });

        test('los enlaces de las migas de pan llevan a páginas que existen', async ({
            page,
        }) => {
            await iniciarSesion(page, rol);
            const problemas: string[] = [];

            for (const url of recorrido[rol]) {
                const respuesta = await page.goto(url);
                if ((respuesta?.status() ?? 0) >= 400) continue;

                const enlaces = await page
                    .locator('nav[aria-label="breadcrumb"] a')
                    .evaluateAll((nodos) =>
                        nodos.map((nodo) => ({
                            texto: nodo.textContent?.trim() ?? '',
                            href:
                                (nodo as HTMLAnchorElement).getAttribute(
                                    'href',
                                ) ?? '',
                        })),
                    );

                for (const { texto, href } of enlaces) {
                    if (href === '#' || href === '') {
                        problemas.push(
                            `${url} -> la miga "${texto}" no lleva a ningún sitio (href="${href}")`,
                        );
                        continue;
                    }
                    const destino = await page.request.get(href);
                    if (destino.status() >= 400) {
                        problemas.push(
                            `${url} -> la miga "${texto}" (${href}) responde HTTP ${destino.status()}`,
                        );
                    }
                }
            }

            expect(problemas, problemas.join('\n')).toEqual([]);
        });
    });
}
