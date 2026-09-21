import { defineConfig } from '@playwright/test';
import path from 'node:path';

const puerto = 8765;
const raiz = process.cwd();
const dirE2e = path.join(raiz, 'storage', 'e2e');

// Entorno del servidor de pruebas: base SQLite aislada (la tuya no se toca), PGP de
// respaldo (rápido) y assets compilados aunque exista un servidor de Vite abierto.
export const entornoE2e = {
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: `http://127.0.0.1:${puerto}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(dirE2e, 'e2e.sqlite'),
    PGP_DRIVER: 'fallback',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    VITE_HOT_FILE: path.join(dirE2e, 'sin-hot'),
};

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 45_000,
    expect: { timeout: 8_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL: `http://127.0.0.1:${puerto}`,
        locale: 'es-ES',
        screenshot: 'only-on-failure',
        trace: 'off',
    },
    outputDir: 'storage/e2e/resultados',
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${puerto} --no-reload`,
        url: `http://127.0.0.1:${puerto}/login`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: entornoE2e,
    },
});
