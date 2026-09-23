import { expect, test, type Page } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

// Los avisos nacen del ciclo de apelación que ejecuta apelaciones.spec.ts (corre antes, en orden
// alfabético) y del propio seed. Aquí solo se comprueba lo que ve cada persona en su campana.
test.describe.configure({ mode: 'serial' });

async function sinLeer(page: Page): Promise<number> {
    const contador = page.locator('[data-test="campana-contador"]');
    if ((await contador.count()) === 0) return 0;
    return Number.parseInt((await contador.innerText()).replace('+', ''), 10);
}

async function abrirCampana(page: Page): Promise<void> {
    await page.locator('[data-test="campana"]').click();
    await expect(page.locator('[data-test="campana-panel"]')).toBeVisible();
}

test.describe('Campana de notificaciones', () => {
    test('el sancionado ve los avisos de su sanción y de su apelación, y al abrir uno baja el contador', async ({
        page,
    }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'sancionado');

        const antes = await sinLeer(page);
        expect(antes).toBeGreaterThanOrEqual(2);

        await abrirCampana(page);
        const panel = page.locator('[data-test="campana-panel"]');
        await expect(panel).toContainText('Se te aplicó una sanción');
        await expect(panel).toContainText('Tu apelación fue aprobada');

        // Abrir el aviso de la apelación lleva a su seguimiento y lo marca como leído.
        await panel
            .locator('[data-test="campana-aviso"]', {
                hasText: 'Tu apelación fue aprobada',
            })
            .click();
        await page.waitForURL(/\/reputacion\/apelaciones\/\d+$/);
        await expect(page.locator('[data-test="estado-apelacion"]')).toHaveText(
            'Aprobada',
        );
        expect(await sinLeer(page)).toBe(antes - 1);

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('la página de notificaciones filtra las no leídas y permite marcarlas todas', async ({
        page,
    }) => {
        await iniciarSesion(page, 'sancionado');
        await page.goto('/notificaciones');

        const todas = await page.locator('[data-test="aviso"]').count();
        expect(todas).toBeGreaterThanOrEqual(2);

        await page.locator('[data-test="filtro-no-leidas"]').click();
        await page.waitForURL(/filtro=no_leidas/);
        const noLeidas = await page.locator('[data-test="aviso"]').count();
        expect(noLeidas).toBeGreaterThanOrEqual(1);
        expect(noLeidas).toBeLessThan(todas);
        for (const aviso of await page.locator('[data-test="aviso"]').all()) {
            await expect(aviso).toHaveAttribute('data-leida', 'false');
        }

        await page.locator('[data-test="marcar-todas-pagina"]').click();
        await expect(
            page.locator('[data-test="campana-contador"]'),
        ).toHaveCount(0);

        // Sin avisos pendientes, el filtro lo dice.
        await expect(page.getByText('No tienes avisos sin leer')).toBeVisible();
    });

    test('el administrador recibe el aviso de la apelación pendiente y lleva a su detalle', async ({
        page,
    }) => {
        await iniciarSesion(page, 'admin');
        await page.goto('/notificaciones');

        const aviso = page
            .locator('[data-test="aviso"]', {
                hasText: 'Nueva apelación pendiente',
            })
            .first();
        await expect(aviso).toBeVisible();
        await aviso
            .getByRole('link', { name: 'Nueva apelación pendiente' })
            .click();

        await page.waitForURL(/\/moderacion\/apelaciones\/\d+$/);
        await expect(page.locator('[data-test="traza"]')).toBeVisible();
    });

    test('el moderador que sancionó se entera de cómo terminó la apelación', async ({
        page,
    }) => {
        await iniciarSesion(page, 'moderador');
        await abrirCampana(page);

        await expect(page.locator('[data-test="campana-panel"]')).toContainText(
            'Apelación aprobada',
        );
    });

    test('quien no tiene avisos ve la campana vacía', async ({ page }) => {
        await iniciarSesion(page, 'investigador');

        expect(await sinLeer(page)).toBe(0);
        await abrirCampana(page);
        await expect(page.locator('[data-test="campana-vacia"]')).toHaveText(
            'No tienes notificaciones.',
        );

        // Escape cierra el panel.
        await page.keyboard.press('Escape');
        await expect(page.locator('[data-test="campana-panel"]')).toHaveCount(
            0,
        );
    });
});
