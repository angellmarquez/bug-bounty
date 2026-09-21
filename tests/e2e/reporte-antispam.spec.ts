import { expect, test, type Page } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

/** Recorre el asistente de "Crear reporte" para el programa 1 y pulsa "Guardar y enviar". */
async function guardarYEnviar(page: Page, titulo: string): Promise<void> {
    await page.goto('/reportes/crear?programa=1');
    await page.locator('#titulo').fill(titulo);
    await page.locator('#descripcion').fill(`Descripción de "${titulo}" para la prueba de envío masivo.`);
    await page.getByRole('button', { name: /Siguiente/ }).click();
    await page.getByRole('button', { name: /Siguiente/ }).click();
    await page.getByRole('button', { name: /Siguiente/ }).click();
    await page.getByRole('button', { name: /Guardar y enviar/ }).click();
}

test('un investigador no puede enviar informes en masa a un programa', async ({ page }) => {
    const errores = vigilarErrores(page);
    await iniciarSesion(page, 'investigador');

    // El investigador de los datos de ejemplo ya tiene 2 informes enviados al programa 1:
    // el tercero entra (límite: 3 por hora y programa)...
    await guardarYEnviar(page, 'Tercer informe legítimo (E2E antispam)');
    await page.waitForURL(/\/reportes\/\d+$/, { timeout: 15_000 });
    await expect(page.getByText('Tercer informe legítimo (E2E antispam)').first()).toBeVisible();

    // ...y el cuarto se bloquea con un aviso claro, sin crear el informe.
    await guardarYEnviar(page, 'Cuarto informe (E2E antispam)');
    const aviso = page.locator('[data-test="aviso-limite"]');
    await expect(aviso).toBeVisible();
    await expect(aviso).toContainText('Ya enviaste 3 informes');
    await expect(aviso).toContainText('guardarlo como borrador');
    await expect(page).toHaveURL(/\/reportes\/crear/);

    // Guardarlo como borrador sí está permitido.
    await page.getByRole('button', { name: /Guardar borrador/ }).click();
    await page.waitForURL(/\/reportes\/\d+$/, { timeout: 15_000 });
    await expect(page.getByText('Cuarto informe (E2E antispam)').first()).toBeVisible();

    expect(errores, errores.join('\n')).toEqual([]);
});
