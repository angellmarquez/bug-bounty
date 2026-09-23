import { expect, test, type Page } from '@playwright/test';
import { iniciarSesion, vigilarErrores, type Rol } from './helpers';

async function destinoDelBotonVolver(
    page: Page,
    url: string,
): Promise<string | null> {
    await page.goto(url);
    const boton = page.locator('[data-test="boton-volver"]').first();
    await expect(boton, `${url}: falta el botón de volver`).toBeVisible();
    return boton.getAttribute('href');
}

/** [rol, página, a dónde debe llevar "Volver"] */
const esperados: [Rol, string, string][] = [
    ['moderador', '/reportes/1', '/moderacion/programas/1'],
    ['admin', '/reportes/1', '/moderacion/programas/1'],
    ['empresa', '/reportes/2', '/empresa/reportes'],
    ['investigador', '/reportes/1', '/reportes'],
    ['investigador', '/reportes/3/editar', '/reportes/3'],
    ['investigador', '/reportes/crear?programa=1', '/reportes'],
    ['investigador', '/programas/1', '/programas'],
    ['empresa', '/programas/1', '/empresa'],
    ['moderador', '/programas/1', '/moderacion'],
    ['admin', '/programas/1', '/moderacion'],
    ['empresa', '/gestion/programas/crear', '/empresa'],
    ['empresa', '/gestion/programas/1/editar', '/programas/1'],
    ['moderador', '/moderacion/programas/1', '/moderacion'],
    ['empresa', '/empresa/reportes', '/empresa'],
];

for (const [rol, url, destino] of esperados) {
    test(`volver: ${rol} en ${url} vuelve a ${destino}`, async ({ page }) => {
        await iniciarSesion(page, rol);
        const href = await destinoDelBotonVolver(page, url);
        expect(new URL(href ?? '', 'http://x').pathname).toBe(destino);

        await page.locator('[data-test="boton-volver"]').first().click();
        await page.waitForURL((actual) => actual.pathname === destino, {
            timeout: 10_000,
        });
    });
}

test('el menú resalta la sección también en las páginas hijas', async ({
    page,
}) => {
    await iniciarSesion(page, 'investigador');
    await page.goto('/reportes/1');
    await expect(
        page.locator(
            '[data-slot="sidebar"] [data-sidebar="menu-button"][data-active="true"]',
        ),
    ).toHaveText(/Mis Reportes/);

    await page.context().clearCookies();
    await iniciarSesion(page, 'empresa');
    await page.goto('/empresa/reportes');
    const activas = page.locator(
        '[data-slot="sidebar"] [data-sidebar="menu-button"][data-active="true"]',
    );
    await expect(activas).toHaveCount(1);
    await expect(activas).toHaveText(/Reportes recibidos/);
});

test.describe('marca de moderador', () => {
    for (const rol of ['doble', 'moderador'] as const) {
        test(`${rol}: se identifica como moderador en el dashboard y en su perfil`, async ({
            page,
        }) => {
            await iniciarSesion(page, rol);

            await page.goto('/dashboard');
            await expect(
                page
                    .locator(
                        '[data-test="roles-usuario"] [data-rol="moderador"]',
                    )
                    .first(),
            ).toBeVisible();

            await page.goto('/settings/profile');
            const roles = page.locator('[data-test="roles-usuario"]');
            await expect(roles.locator('[data-rol="moderador"]')).toBeVisible();
            await expect(roles).toContainText('Revisas los informes');
        });
    }

    test('el investigador que también es moderador conserva su rol de investigador', async ({
        page,
    }) => {
        await iniciarSesion(page, 'doble');
        await page.goto('/settings/profile');
        const roles = page.locator('[data-test="roles-usuario"]');
        await expect(roles.locator('[data-rol="investigador"]')).toBeVisible();
        await expect(roles.locator('[data-rol="moderador"]')).toBeVisible();
    });

    test('un investigador sin rol de moderador no lleva la marca', async ({
        page,
    }) => {
        await iniciarSesion(page, 'investigador');
        await page.goto('/dashboard');
        await expect(
            page
                .locator(
                    '[data-test="roles-usuario"] [data-rol="investigador"]',
                )
                .first(),
        ).toBeVisible();
        await expect(page.locator('[data-rol="moderador"]')).toHaveCount(0);
    });
});

test('si una página falla, el menú y la cabecera siguen funcionando y se puede seguir navegando', async ({
    page,
}) => {
    const errores = vigilarErrores(page);
    await iniciarSesion(page, 'investigador');
    await page.goto('/dashboard');

    // Simula datos corruptos del servidor al abrir "Mis Reportes" (respuesta de Inertia sin `reportes`).
    await page.route(/\/reportes$/, async (route) => {
        if (route.request().headers()['x-inertia'] !== 'true')
            return route.continue();
        const respuesta = await route.fetch();
        const cuerpo = await respuesta.json();
        cuerpo.props.reportes = null;
        await route.fulfill({ response: respuesta, json: cuerpo });
    });

    await page
        .locator('[data-slot="sidebar"] a[href$="/reportes"]')
        .first()
        .click();

    await expect(page.locator('[data-test="error-de-pagina"]')).toBeVisible();
    await expect(page.locator('[data-slot="sidebar"]').first()).toBeVisible();
    await expect(page.locator('[data-slot="sidebar"]')).toContainText(
        'Programas',
    );
    await expect(page.locator('nav[aria-label="breadcrumb"]')).toBeVisible();

    // El error no se queda pegado: al ir a otra página todo vuelve a la normalidad.
    await page
        .locator('[data-slot="sidebar"] a[href$="/dashboard"]')
        .first()
        .click();
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await expect(page.locator('[data-test="error-de-pagina"]')).toHaveCount(0);
    await expect(
        page.locator('[data-test="roles-usuario"]').first(),
    ).toBeVisible();

    // El fallo simulado sí se registra en consola, pero no rompe la aplicación.
    expect(
        errores.every((error) => /Cannot read|null|undefined/.test(error)),
    ).toBe(true);
});
