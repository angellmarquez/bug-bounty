import { expect, test, type Page } from '@playwright/test';
import path from 'node:path';
import { iniciarSesion, vigilarErrores } from './helpers';

// Una palabra sin espacios y un enlace larguísimos: el navegador no los parte solo y, sin
// reglas de corte, empujaban la tarjeta fuera de la pantalla.
const PALABRA_LARGA = 'A'.repeat(260);
const ENLACE_LARGO = `https://acme.test/${'segmento-muy-largo/'.repeat(12)}fin`;
const DESCRIPCION = `Programa con una descripción muy larga. ${PALABRA_LARGA}\nMás detalles en ${ENLACE_LARGO} y reglas de participación.`;

const capturas = path.join(process.cwd(), 'storage', 'e2e', 'capturas');

/**
 * Nada se sale de su sitio en horizontal: ni la página ni los contenedores con scroll (las
 * páginas usan `overflow-x-auto`, que escondía el desborde dentro de sí en vez de en la ventana).
 * Los bloques de código (`pre`) sí pueden desplazarse.
 */
async function sinDesbordeHorizontal(page: Page): Promise<void> {
    const desbordes = await page.evaluate(() => {
        const encontrados: string[] = [];
        const raiz = document.documentElement;

        if (raiz.scrollWidth - raiz.clientWidth > 1) {
            encontrados.push(
                `documento (+${raiz.scrollWidth - raiz.clientWidth}px)`,
            );
        }

        for (const el of Array.from(
            document.body.querySelectorAll<HTMLElement>('*'),
        )) {
            const { overflowX } = getComputedStyle(el);

            if (
                (overflowX === 'auto' || overflowX === 'scroll') &&
                el.tagName !== 'PRE' &&
                el.scrollWidth - el.clientWidth > 1
            ) {
                encontrados.push(
                    `${el.tagName.toLowerCase()}.${el.className.toString().split(' ').slice(0, 4).join('.')} (+${el.scrollWidth - el.clientWidth}px)`,
                );
            }
        }

        return encontrados;
    });

    expect(desbordes, `contenido desbordado:\n${desbordes.join('\n')}`).toEqual(
        [],
    );
}

test.describe('Textos largos', () => {
    test('una descripción de programa larga no desborda la página', async ({
        page,
    }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'empresa');

        await page.goto('/gestion/programas/1/editar');
        const descripcion = page.locator('#descripcion');
        await descripcion.fill(DESCRIPCION);
        // El contador avisa del límite antes de enviar.
        await expect(
            page.locator('[data-test="contador-descripcion"]'),
        ).toContainText(`${DESCRIPCION.length} / 5000`);
        await sinDesbordeHorizontal(page);

        await page.getByRole('button', { name: /Guardar Cambios/ }).click();
        await page.waitForURL(/\/programas\/1$|\/gestion\/programas/, {
            timeout: 15_000,
        });

        for (const ruta of ['/programas/1', '/gestion/programas']) {
            await page.goto(ruta);
            await page.waitForLoadState('networkidle');
            await sinDesbordeHorizontal(page);
            await page.screenshot({
                path: path.join(
                    capturas,
                    `descripcion-larga-${ruta.replaceAll('/', '_')}.png`,
                ),
                fullPage: true,
            });
        }

        // El listado no descifra cada programa: nunca debe mostrar el bloque cifrado.
        await expect(page.getByText(/BEGIN (FAKE )?PGP MESSAGE/)).toHaveCount(
            0,
        );
        await expect(
            page.locator('[data-test="visibilidad-programa"]').first(),
        ).toBeVisible();

        // La descripción completa se ve (descifrada) en la ficha del programa.
        await page.goto('/programas/1');
        await expect(
            page.getByText('Programa con una descripción muy larga.').first(),
        ).toBeVisible();

        // El investigador ve el programa igual de bien, también en un móvil.
        await iniciarSesion(page, 'investigador');
        for (const ancho of [1280, 390]) {
            await page.setViewportSize({ width: ancho, height: 900 });
            for (const ruta of ['/programas/1', '/programas']) {
                await page.goto(ruta);
                await page.waitForLoadState('networkidle');
                await sinDesbordeHorizontal(page);
                await page.screenshot({
                    path: path.join(
                        capturas,
                        `descripcion-larga-inv-${ancho}-${ruta.replaceAll('/', '_')}.png`,
                    ),
                    fullPage: true,
                });
            }
        }

        expect(errores, errores.join('\n')).toEqual([]);
    });

    test('la descripción no admite más caracteres que el límite del servidor', async ({
        page,
    }) => {
        await iniciarSesion(page, 'empresa');
        await page.goto('/gestion/programas/crear');

        await expect(page.locator('#descripcion')).toHaveAttribute(
            'maxlength',
            '5000',
        );
        await expect(page.locator('#bugs_buscados')).toHaveAttribute(
            'maxlength',
            '3000',
        );
    });
});

test.describe('Asistente de informes', () => {
    test('si el servidor rechaza un dato de un paso anterior, vuelve a ese paso y lo explica', async ({
        page,
    }) => {
        const errores = vigilarErrores(page);
        await iniciarSesion(page, 'investigador');
        await page.goto('/reportes/crear?programa=1');

        // El navegador ya no deja pasar de 255, así que se quita el límite para simular un
        // dato que solo el servidor rechaza.
        await expect(page.locator('#titulo')).toHaveAttribute(
            'maxlength',
            '255',
        );
        await page
            .locator('#titulo')
            .evaluate((campo) => campo.removeAttribute('maxlength'));
        await page.locator('#titulo').fill(`XSS ${'x'.repeat(300)}`);
        await page
            .locator('#descripcion')
            .fill(
                'Pasos: 1) abrir el buscador 2) enviar un script y ver que se ejecuta en la página.',
            );
        await page.getByRole('button', { name: /Siguiente/ }).click();
        await page.getByRole('button', { name: /Siguiente/ }).click();
        await page.locator('#url').fill('https://app.acme.test/buscar');
        await page
            .locator('#pasos')
            .fill('1) abrir el buscador 2) enviar <script>alert(1)</script>');
        await page.getByRole('button', { name: /Siguiente/ }).click();
        await expect(page.getByText('Revisión del reporte')).toBeVisible();

        await page.getByRole('button', { name: /Guardar y enviar/ }).click();

        // Vuelve al paso 1, con el error junto al campo y en el resumen.
        const resumen = page.locator('[data-test="errores-servidor"]');
        await expect(resumen).toContainText(
            'El título no puede exceder 255 caracteres.',
        );
        await expect(page.locator('#titulo')).toBeVisible();
        await expect(page.getByText('Revisión del reporte')).toHaveCount(0);
        await page.screenshot({
            path: path.join(capturas, 'informe-error-servidor.png'),
            fullPage: true,
        });

        expect(errores, errores.join('\n')).toEqual([]);
    });
});
