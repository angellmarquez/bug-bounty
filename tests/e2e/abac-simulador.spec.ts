import { expect, test, type Page } from '@playwright/test';
import { iniciarSesion, vigilarErrores } from './helpers';

/** Rellena el simulador y lo ejecuta; devuelve el veredicto que muestra la página. */
async function simular(
    page: Page,
    usuario: string,
    accion: string,
    tipo: 'reporte' | 'programa' | 'apelacion' | 'ninguno',
    recurso?: string,
): Promise<string> {
    const sujeto = page
        .locator('#abac-usuario option', { hasText: usuario })
        .first();
    await page
        .locator('#abac-usuario')
        .selectOption((await sujeto.getAttribute('value')) ?? '');
    await page.locator('#abac-accion').selectOption(accion);
    await page.locator('#abac-tipo-recurso').selectOption(tipo);
    if (recurso) {
        const opcion = page
            .locator('#abac-recurso-id option', { hasText: recurso })
            .first();
        await page
            .locator('#abac-recurso-id')
            .selectOption((await opcion.getAttribute('value')) ?? '');
    }

    const respuesta = page.waitForResponse((r) =>
        r.url().endsWith('/admin/abac/simular'),
    );
    await page
        .getByRole('button', { name: /Ejecutar Simulación ABAC/ })
        .click();
    expect((await respuesta).status()).toBe(200);

    return (
        await page.getByText(/ACCESO (PERMITIDO|DENEGADO)/).innerText()
    ).trim();
}

test.describe('Simulador ABAC (administrador)', () => {
    test('evalúa en el navegador y explica la decisión', async ({ page }) => {
        const errores = vigilarErrores(page);
        page.on('dialog', (d) => {
            errores.push(`alerta: ${d.message()}`);
            void d.dismiss();
        });
        await iniciarSesion(page, 'admin');
        await page.goto('/admin/abac/simulador');

        expect(
            await simular(
                page,
                'Investigador E2E [investigador]',
                'reportes.crear',
                'programa',
                'Programa Acme E2E',
            ),
        ).toBe('ACCESO PERMITIDO');
        await expect(page.getByText('Regla de impacto')).toBeVisible();

        expect(
            await simular(
                page,
                'Empresa E2E',
                'reportes.crear',
                'programa',
                'Programa Acme E2E',
            ),
        ).toBe('ACCESO DENEGADO');
        await expect(
            page.getByText('[denegar-crear-reportes-a-empresa]').first(),
        ).toBeVisible();

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('da la misma respuesta que la aplicación: la empresa ve su propio programa en borrador', async ({
        page,
    }) => {
        await iniciarSesion(page, 'admin');
        await page.goto('/admin/abac/simulador');

        // En la app real la empresa abre su borrador (ProgramaController pasa su empresa al motor).
        expect(
            await simular(
                page,
                'Empresa E2E',
                'programas.ver',
                'programa',
                'Programa en borrador E2E',
            ),
        ).toBe('ACCESO PERMITIDO');
        // Un investigador cualquiera no lo ve.
        expect(
            await simular(
                page,
                'Investigador E2E [investigador]',
                'programas.ver',
                'programa',
                'Programa en borrador E2E',
            ),
        ).toBe('ACCESO DENEGADO');
    });
});
