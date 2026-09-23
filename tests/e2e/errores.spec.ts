import { expect, test } from '@playwright/test';
import { iniciarSesion } from './helpers';

test.describe('Páginas de error', () => {
    test('un 403 se muestra con el diseño de la aplicación, sin pantalla blanca', async ({
        page,
    }) => {
        await iniciarSesion(page, 'investigador');
        const respuesta = await page.goto('/admin/pgp');
        expect(respuesta?.status()).toBe(403);

        const error = page.locator('[data-test="pagina-error"]');
        await expect(error).toHaveAttribute('data-status', '403');
        await expect(page.locator('[data-test="error-titulo"]')).toHaveText(
            'No tienes permiso para ver esto',
        );
        // Conserva el menú lateral y no muestra el texto en inglés de Laravel ni nombres de reglas.
        await expect(
            page.locator('[data-slot="sidebar"]').first(),
        ).toBeVisible();
        await expect(page.locator('body')).not.toContainText('Forbidden');
        await expect(page.locator('body')).not.toContainText('abac');

        await page.locator('[data-test="error-inicio"]').click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('una dirección que no existe da un 404 con salida al panel', async ({
        page,
    }) => {
        await iniciarSesion(page, 'moderador');
        const respuesta = await page.goto('/esta-pagina-no-existe');
        expect(respuesta?.status()).toBe(404);

        await expect(page.locator('[data-test="error-titulo"]')).toHaveText(
            'Página no encontrada',
        );
        await expect(
            page.locator('[data-slot="sidebar"]').first(),
        ).toBeVisible();
        await expect(page.locator('[data-test="error-inicio"]')).toHaveText(
            /Ir al panel/,
        );
    });

    test('un informe inexistente da 404 sin detalles internos', async ({
        page,
    }) => {
        await iniciarSesion(page, 'investigador');
        const respuesta = await page.goto('/reportes/999999');
        expect(respuesta?.status()).toBe(404);

        await expect(page.locator('[data-test="error-titulo"]')).toHaveText(
            'Página no encontrada',
        );
        await expect(page.locator('body')).not.toContainText(
            'No query results',
        );
    });

    test('un visitante sin sesión ve el 404 a pantalla completa con salida al inicio', async ({
        page,
    }) => {
        const respuesta = await page.goto('/esta-pagina-no-existe');
        expect(respuesta?.status()).toBe(404);

        await expect(page.locator('[data-test="error-titulo"]')).toHaveText(
            'Página no encontrada',
        );
        await expect(page.locator('[data-slot="sidebar"]')).toHaveCount(0);
        await page.locator('[data-test="error-inicio"]').click();
        await expect(page).toHaveURL(/\/$/);
    });

    test('el botón Volver regresa a la página anterior', async ({ page }) => {
        await iniciarSesion(page, 'investigador');
        await page.goto('/dashboard');
        await page.goto('/esta-pagina-no-existe');

        await page.locator('[data-test="error-volver"]').click();
        await expect(page).toHaveURL(/\/dashboard/);
    });
});
