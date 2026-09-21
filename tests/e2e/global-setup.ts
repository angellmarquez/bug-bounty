import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { entornoE2e } from '../../playwright.config';
import { cuentas, rutaEstado, type Rol } from './helpers';

/**
 * Prepara una base SQLite nueva y aislada con cuentas de prueba (una por rol) y datos
 * de ejemplo, y guarda una sesión iniciada por cada rol. No usa ni modifica
 * database/database.sqlite.
 *
 * Las sesiones se guardan una sola vez para no chocar con el límite de intentos de
 * inicio de sesión de Fortify (5 por minuto) al ejecutar muchas pruebas.
 */
export default async function globalSetup(): Promise<void> {
    const dir = path.dirname(entornoE2e.DB_DATABASE);
    fs.mkdirSync(dir, { recursive: true });
    fs.rmSync(entornoE2e.DB_DATABASE, { force: true });
    fs.writeFileSync(entornoE2e.DB_DATABASE, '');

    const env = { ...process.env, ...entornoE2e };
    const opciones = { env, stdio: 'inherit' as const, cwd: process.cwd() };

    execFileSync(
        'php',
        ['artisan', 'migrate:fresh', '--force', '--no-interaction'],
        opciones,
    );
    execFileSync('php', ['tests/e2e/seed.php'], opciones);

    // El servidor web de Playwright arranca después de este paso: se levanta uno propio solo
    // para iniciar las sesiones y se cierra al terminar.
    const puerto = 8766;
    const servidor = (await import('node:child_process')).spawn(
        'php',
        [
            'artisan',
            'serve',
            '--host=127.0.0.1',
            `--port=${puerto}`,
            '--no-reload',
        ],
        {
            env: { ...env, APP_URL: `http://127.0.0.1:${puerto}` },
            stdio: 'ignore',
        },
    );

    try {
        await esperarServidor(`http://127.0.0.1:${puerto}/login`);
        const navegador = await chromium.launch();

        for (const rol of Object.keys(cuentas) as Rol[]) {
            const contexto = await navegador.newContext({
                baseURL: `http://127.0.0.1:${puerto}`,
            });
            const page = await contexto.newPage();
            await page.goto('/login');
            await page
                .getByLabel(/correo|email/i)
                .first()
                .fill(cuentas[rol]);
            await page
                .locator('input[type="password"]')
                .first()
                .fill('password');
            await page.locator('form button[type="submit"]').first().click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'), {
                timeout: 20_000,
            });
            await contexto.storageState({ path: rutaEstado(rol) });
            await contexto.close();
        }

        await navegador.close();
    } finally {
        cerrar(servidor.pid);
    }
}

async function esperarServidor(url: string): Promise<void> {
    const limite = Date.now() + 30_000;
    while (Date.now() < limite) {
        try {
            const respuesta = await fetch(url);
            if (respuesta.status < 500) return;
        } catch {
            // el servidor todavía no responde
        }
        await new Promise((resolver) => setTimeout(resolver, 300));
    }
    throw new Error(`El servidor de pruebas no respondió en ${url}`);
}

/** En Windows `artisan serve` deja un proceso hijo vivo: hay que cerrar todo el árbol. */
function cerrar(pid: number | undefined): void {
    if (pid === undefined) return;

    if (process.platform === 'win32') {
        try {
            execFileSync('taskkill', ['/pid', String(pid), '/T', '/F'], {
                stdio: 'ignore',
            });
        } catch {
            // ya había terminado
        }
        return;
    }

    process.kill(pid);
}
