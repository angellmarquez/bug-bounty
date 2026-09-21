import { expect, type BrowserContext, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

type Cookie = Parameters<BrowserContext['addCookies']>[0][number];

export type Rol = 'admin' | 'moderador' | 'investigador' | 'empresa' | 'doble' | 'sancionado';

export const cuentas: Record<Rol, string> = {
    admin: 'admin@e2e.test',
    moderador: 'moderador@e2e.test',
    investigador: 'investigador@e2e.test',
    empresa: 'empresa@e2e.test',
    doble: 'doble@e2e.test',
    sancionado: 'sancionado@e2e.test',
};

export function rutaEstado(rol: Rol): string {
    return path.join(process.cwd(), 'storage', 'e2e', `sesion-${rol}.json`);
}

/**
 * Deja la sesión de `rol` iniciada usando la que global-setup guardó (así no se agota el
 * límite de intentos de login) y comprueba que el layout de la aplicación se ve.
 */
export async function iniciarSesion(page: Page, rol: Rol): Promise<void> {
    const estado = JSON.parse(fs.readFileSync(rutaEstado(rol), 'utf-8')) as {
        cookies: Cookie[];
    };

    await page.context().clearCookies();
    await page.context().addCookies(estado.cookies);
    await page.goto('/dashboard');
    await expect(page.locator('[data-slot="sidebar"]').first()).toBeVisible();
}

/** Errores de JavaScript que aparecen mientras se navega (excepto ruido conocido del navegador). */
export function vigilarErrores(page: Page): string[] {
    const errores: string[] = [];

    page.on('pageerror', (error) =>
        errores.push(`pageerror: ${error.message}`),
    );
    page.on('console', (mensaje) => {
        if (
            mensaje.type() === 'error' &&
            !/favicon|Failed to load resource: the server responded with a status of (403|404)/.test(
                mensaje.text(),
            )
        ) {
            errores.push(`console.error: ${mensaje.text()}`);
        }
    });

    return errores;
}

/** Entradas visibles del menú lateral. */
export async function entradasDelMenu(page: Page): Promise<string[]> {
    const textos = await page
        .locator(
            '[data-slot="sidebar"] [data-sidebar="menu-button"], [data-slot="sidebar"] a',
        )
        .allInnerTexts();

    return textos.map((texto) => texto.trim()).filter(Boolean);
}
