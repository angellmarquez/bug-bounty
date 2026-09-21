import { expect, test } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

// El ciclo completo depende del estado que va dejando cada paso: se ejecutan en orden.
test.describe.configure({ mode: 'serial' });

test.describe('Ciclo de una apelación', () => {
    test('el sancionado apela y ve el seguimiento como pendiente', async ({ page }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'sancionado');

        await page.goto('/reputacion/sanciones');
        await page.getByRole('button', { name: 'Apelar' }).first().click();
        await page.locator('#motivo-apelacion').fill('El informe era legítimo, adjunto la evidencia original.');
        await page.getByRole('button', { name: 'Enviar apelación' }).click();
        await page.waitForURL(/\/reputacion$|\/reputacion\?/, { timeout: 15_000 }).catch(() => {});

        // La página de apelaciones lista la nueva y lleva al seguimiento.
        await page.goto('/reputacion/apelaciones');
        await page.locator('[data-test="ver-seguimiento"]').first().click();
        await page.waitForURL(/\/reputacion\/apelaciones\/\d+$/);

        await expect(page.locator('[data-test="estado-apelacion"]')).toHaveText('Pendiente');
        await expect(page.locator('[data-test="mensaje-estado"]')).toContainText('pendiente');
        await expect(page.locator('[data-test="paso-traza"]')).toHaveCount(1);
        await expect(page.locator('[data-test="cadena-valida"]')).toBeVisible();

        // Ya apeló: la sanción no vuelve a ofrecer el botón.
        await page.goto('/reputacion/sanciones');
        await expect(page.getByRole('button', { name: 'Apelar' })).toHaveCount(0);

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('el moderador que aplicó la sanción no puede resolverla', async ({ page }) => {
        await iniciarSesion(page, 'moderador');
        await page.goto('/moderacion/apelaciones');

        const tarjeta = page.locator('[data-test="apelacion-card"]').first();
        await expect(tarjeta).toContainText('Investigador Sancionado E2E');
        await expect(tarjeta.locator('[data-test="bloqueo-resolver"]')).toContainText('Tú aplicaste esta sanción');
        await expect(tarjeta.locator('[data-test="resolver-form"]')).toHaveCount(0);
    });

    test('otro moderador la aprueba y queda a su nombre', async ({ page }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'doble');
        await page.goto('/moderacion/apelaciones');

        const tarjeta = page.locator('[data-test="apelacion-card"]').first();
        await expect(tarjeta.locator('[data-test="sanciono"]')).toContainText('Moderador E2E');

        // Sin nota no se puede resolver.
        await tarjeta.locator('[data-test="aprobar-apelacion"]').click();
        await expect(tarjeta.getByText('Escribe una nota explicando la decisión.')).toBeVisible();

        await tarjeta.getByPlaceholder('Nota de resolución (obligatoria)...').fill('Se revisó la evidencia: la apelación es fundada.');
        await tarjeta.locator('[data-test="aprobar-apelacion"]').click();

        await expect(page.locator('[data-test="apelacion-card"]').first()).toContainText('Resuelta por Investigador Moderador E2E');
        await expect(page.locator('[data-test="resolver-form"]')).toHaveCount(0);
        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('el sancionado ve la aprobación y solo el rol de quien decidió', async ({ page }) => {
        await iniciarSesion(page, 'sancionado');
        await page.goto('/reputacion/apelaciones');
        await page.locator('[data-test="ver-seguimiento"]').first().click();
        await page.waitForURL(/\/reputacion\/apelaciones\/\d+$/);

        await expect(page.locator('[data-test="estado-apelacion"]')).toHaveText('Aprobada');
        await expect(page.locator('[data-test="nota-resolucion"]')).toContainText('la apelación es fundada');
        await expect(page.locator('[data-test="paso-traza"]')).toHaveCount(2);
        await expect(page.locator('[data-test="paso-actor"]').nth(1)).toHaveText('Moderador');
        await expect(page.locator('[data-test="cadena-valida"]')).toBeVisible();

        // Privacidad: ni el nombre del moderador ni su IP aparecen en la página del sancionado.
        const cuerpo = await page.locator('body').innerText();
        expect(cuerpo).not.toContain('Investigador Moderador E2E');
        expect(cuerpo).not.toContain('IP ');
    });

    test('el administrador ve quién hizo cada paso, su IP y que el registro es íntegro', async ({ page }) => {
        await iniciarSesion(page, 'admin');
        await page.goto('/moderacion/apelaciones');
        await page.locator('[data-test="ver-apelacion"]').first().click();
        await page.waitForURL(/\/moderacion\/apelaciones\/\d+$/);

        await expect(page.locator('[data-test="estado-apelacion"]')).toHaveText('Aprobada');
        await expect(page.locator('[data-test="sanciono"]')).toContainText('Moderador E2E');
        await expect(page.locator('[data-test="paso-actor"]').nth(0)).toContainText('Investigador Sancionado E2E · Investigador');
        await expect(page.locator('[data-test="paso-actor"]').nth(1)).toContainText('Investigador Moderador E2E · Moderador');
        await expect(page.locator('[data-test="traza"]')).toContainText('IP ');
        await expect(page.locator('[data-test="traza"]')).toContainText('Huella ');
        await expect(page.locator('[data-test="cadena-valida"]')).toBeVisible();
    });

    test('un investigador sin sanciones no puede entrar al panel de resolución', async ({ page }) => {
        await iniciarSesion(page, 'investigador');
        const respuesta = await page.goto('/moderacion/apelaciones');
        expect(respuesta?.status()).toBe(403);
    });
});
