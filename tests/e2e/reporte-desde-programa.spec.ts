import { expect, test } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

test.describe('Reportar desde un programa', () => {
    test('el programa abierto queda fijo: no se pide elegirlo y el reporte es sobre él', async ({
        page,
    }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'investigador');

        await page.goto('/programas/1');
        await page
            .getByRole('link', { name: /Reportar un bug/ })
            .first()
            .click();
        await page.waitForURL(/\/reportes\/crear\?programa=1/);

        // El programa se muestra como dato fijo, sin selector.
        const fijo = page.locator('[data-test="programa-fijo"]');
        await expect(fijo).toContainText('Programa Acme E2E');
        await expect(page.getByText('Seleccionar programa...')).toHaveCount(0);
        await expect(page.locator('#programa_id')).toHaveCount(0);

        // Se puede avanzar sin haber "seleccionado" nada.
        await page
            .locator('#titulo')
            .fill('XSS en el buscador (E2E programa fijo)');
        await page
            .locator('#descripcion')
            .fill(
                'Pasos: 1) abrir el buscador 2) enviar <script>alert(1)</script>',
            );
        await page.getByRole('button', { name: /Siguiente/ }).click();
        await expect(
            page.getByText('Debe seleccionar un programa'),
        ).toHaveCount(0);
        await page.getByRole('button', { name: /Siguiente/ }).click();

        // Paso PoC: el programa tiene poc_schema, se llenan los campos requeridos y se revisa el preview.
        await page.locator('#url').fill('https://app.acme.test/buscar');
        await page
            .locator('#pasos')
            .fill('1) abrir el buscador 2) enviar <script>alert(1)</script>');
        await page.getByRole('button', { name: 'Ver' }).click();
        const preview = page.locator('pre');
        await expect(preview).toContainText('Prueba de Concepto');
        await expect(preview).toContainText('app.acme.test/buscar');

        await page.getByRole('button', { name: /Siguiente/ }).click();

        // Revisión: el programa es el del que se venía.
        await expect(page.getByText('Revisión del reporte')).toBeVisible();
        await expect(page.getByText('Programa Acme E2E').first()).toBeVisible();

        await page.getByRole('button', { name: /Guardar borrador/ }).click();
        await page.waitForURL(/\/reportes\/\d+$/, { timeout: 15_000 });
        await expect(page.getByText('Programa Acme E2E').first()).toBeVisible();

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('sin programa de origen (desde la lista de reportes) sí se elige el programa', async ({
        page,
    }) => {
        await iniciarSesion(page, 'investigador');
        await page.goto('/reportes/crear');

        await expect(page.locator('[data-test="programa-fijo"]')).toHaveCount(
            0,
        );
        await expect(page.getByText('Programa *')).toBeVisible();
    });

    test('el dashboard ya no muestra "Acciones rapidas"', async ({ page }) => {
        for (const rol of [
            'investigador',
            'admin',
            'moderador',
            'empresa',
        ] as const) {
            await iniciarSesion(page, rol);
            await expect(
                page.getByText('Acciones rapidas'),
                `rol ${rol}`,
            ).toHaveCount(0);
        }
    });
});
