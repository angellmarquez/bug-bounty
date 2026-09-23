import { expect, test } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

test.describe('Cifrado de la plataforma (administrador)', () => {
    test('la pantalla solo muestra el estado: no hay botón para generar claves', async ({ page }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'admin');
        await page.goto('/admin/pgp');

        await expect(page.getByText('Clave de Custodia')).toBeVisible();
        await expect(page.getByText('Claves por Empresa')).toBeVisible();
        await expect(page.getByText('La clave privada nunca se muestra')).toBeVisible();
        await expect(page.getByText('no requiere ninguna acción')).toBeVisible();
        // Solo estado: ni huella ni identidad de ninguna clave.
        await expect(page.getByText('Huella')).toHaveCount(0);

        // Nada que pulsar: la plataforma crea su clave sola.
        await expect(page.getByRole('button', { name: /Generar/i })).toHaveCount(0);

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('los demás roles no entran a la pantalla de cifrado', async ({ page }) => {
        for (const rol of ['investigador', 'moderador', 'empresa'] as const) {
            await iniciarSesion(page, rol);
            const respuesta = await page.goto('/admin/pgp');
            expect(respuesta?.status(), `rol ${rol}`).toBe(403);
        }
    });
});
