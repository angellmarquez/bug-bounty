import { expect, test, type Page } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

// Cada paso depende del anterior (invitar → aceptar → publicar → retirar): van en orden.
test.describe.configure({ mode: 'serial' });

async function sinLeer(page: Page): Promise<number> {
    const contador = page.locator('[data-test="campana-contador"]');
    if ((await contador.count()) === 0) return 0;
    return Number.parseInt((await contador.innerText()).replace('+', ''), 10);
}

async function invitarDesdeElPanel(page: Page, correo: string): Promise<void> {
    await page.goto('/empresa');
    await page.locator('[data-test="correo-invitacion"]').fill(correo);
    await page.locator('[data-test="invitar-investigador"]').click();
}

test.describe('Empresa: investigadores que publican programas', () => {
    test('el propietario no puede invitar a un moderador ni a alguien sin cuenta', async ({ page }) => {
        await iniciarSesion(page, 'empresa');

        await invitarDesdeElPanel(page, 'moderador@e2e.test');
        await expect(page.getByText(/Un moderador no puede formar parte de una empresa/).first()).toBeVisible();

        await invitarDesdeElPanel(page, 'nadie-registrado@e2e.test');
        await expect(page.getByText(/No hay ningún usuario registrado/).first()).toBeVisible();

        await expect(page.locator('[data-test="invitacion-pendiente"]')).toHaveCount(0);
    });

    test('el propietario invita a un investigador por su correo y queda pendiente', async ({ page }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'empresa');

        await invitarDesdeElPanel(page, 'invitado@e2e.test');
        await expect(page.getByText(/Invitación enviada a Investigador Invitado E2E/).first()).toBeVisible();
        await expect(page.locator('[data-test="invitacion-pendiente"]')).toContainText('Investigador Invitado E2E');

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('el invitado recibe el aviso, ve qué implica y acepta', async ({ page }) => {
        const errores = vigilarErrores(page);
        page.on('dialog', (dialogo) => dialogo.accept());
        await iniciarSesion(page, 'invitado');

        expect(await sinLeer(page)).toBe(1);
        await expect(page.locator('[data-test="tarjeta-invitaciones"]')).toContainText('Tienes 1 invitación');

        // El aviso de la campana lleva a la invitación.
        await page.locator('[data-test="campana"]').click();
        await page.locator('[data-test="campana-aviso"]', { hasText: 'Te invitaron a formar parte de una empresa' }).click();
        await page.waitForURL(/\/invitaciones$/);

        const tarjeta = page.locator('[data-test="invitacion"]').first();
        await expect(tarjeta).toContainText('No verás los informes');
        await expect(tarjeta).toContainText('no podrás reportar');
        await tarjeta.locator('[data-test="aceptar-invitacion"]').click();

        await page.waitForURL(/\/gestion\/programas/);
        await expect(page.locator('[data-slot="sidebar"]').first()).toContainText('Mi empresa');

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('el propietario se entera y ve al nuevo publicador en su equipo', async ({ page }) => {
        await iniciarSesion(page, 'empresa');

        await page.locator('[data-test="campana"]').click();
        await expect(page.locator('[data-test="campana-panel"]')).toContainText('Investigador Invitado E2E aceptó tu invitación');
        await page.keyboard.press('Escape');

        await page.goto('/empresa');
        const miembro = page.locator('[data-test="miembro"]', { hasText: 'Investigador Invitado E2E' });
        await expect(miembro).toContainText('publicador');
        await expect(page.locator('[data-test="invitacion-pendiente"]')).toHaveCount(0);
    });

    test('el publicador crea un programa de la empresa desde su botón del dashboard', async ({ page }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'invitado');

        await expect(page.locator('[data-test="tarjeta-publicador"]')).toContainText('Publicas para');
        await page.locator('[data-test="publicar-programa"]').click();
        await page.waitForURL(/\/gestion\/programas\/crear/);

        await page.locator('#nombre').fill('Programa del publicador E2E');
        await page.locator('#descripcion').fill('Programa creado por un investigador invitado de la empresa.');
        await page.getByPlaceholder('Valor (ej: *.ejemplo.com)').first().fill('publicador.acme.test');
        await page.getByRole('button', { name: 'Crear Programa' }).click();
        await page.waitForURL(/\/programas\/\d+$/, { timeout: 15_000 });
        await expect(page.getByText('Programa del publicador E2E').first()).toBeVisible();

        await page.goto('/gestion/programas');
        const fila = page.locator('body');
        await expect(fila).toContainText('Programa del publicador E2E');

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('el publicador no ve los informes de la empresa', async ({ page }) => {
        await iniciarSesion(page, 'invitado');

        expect((await page.goto('/reportes/1'))?.status()).toBe(403);
        expect((await page.goto('/empresa/reportes'))?.status()).toBe(403);

        // Su panel de empresa lo lleva a sus programas, sin informes.
        await page.goto('/empresa');
        await expect(page).toHaveURL(/\/gestion\/programas/);
    });

    test('el publicador no puede reportar a los programas de su empresa pero se le explica por qué', async ({ page }) => {
        await iniciarSesion(page, 'invitado');

        await page.goto('/programas/1');
        await expect(page.locator('[data-test="aviso-mi-empresa"]')).toContainText('conflicto de interés');
        await expect(page.getByRole('link', { name: /Reportar un bug/ })).toHaveCount(0);

        // Tampoco aparece entre los programas que se pueden elegir al crear un informe.
        await page.goto('/reportes/crear');
        await page.getByText('Seleccionar programa...').click();
        await expect(page.getByText('Programa Acme E2E')).toHaveCount(0);
    });

    test('el propietario retira al publicador y este vuelve a poder reportar', async ({ page }) => {
        page.on('dialog', (dialogo) => dialogo.accept());
        await iniciarSesion(page, 'empresa');
        await page.goto('/empresa');

        const miembro = page.locator('[data-test="miembro"]', { hasText: 'Investigador Invitado E2E' });
        await miembro.getByRole('button', { name: 'Retirar' }).click();
        await expect(page.locator('[data-test="miembro"]', { hasText: 'Investigador Invitado E2E' })).toHaveCount(0);

        await iniciarSesion(page, 'invitado');
        await expect(page.locator('[data-slot="sidebar"]').first()).not.toContainText('Mi empresa');
        await page.goto('/programas/1');
        await expect(page.getByRole('link', { name: /Reportar un bug/ }).first()).toBeVisible();
        await expect(page.locator('[data-test="aviso-mi-empresa"]')).toHaveCount(0);

        // Y el programa de la empresa vuelve a estar entre los que puede elegir al reportar.
        await page.goto('/reportes/crear');
        await page.getByText('Seleccionar programa...').click();
        await expect(page.getByText('Programa Acme E2E').first()).toBeVisible();
    });
});
