import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

/**
 * Temas visuales de Huella. Terminal y Neón son oscuros; Corporativo es claro;
 * Automático sigue al sistema (Terminal si es oscuro, Corporativo si es claro).
 */
export type Tema = 'terminal' | 'corporativo' | 'neon' | 'auto';
export type TemaResuelto = Exclude<Tema, 'auto'>;

export const TEMAS: readonly Tema[] = [
    'terminal',
    'corporativo',
    'neon',
    'auto',
];
export const TEMA_POR_DEFECTO: Tema = 'terminal';

export type ThemeState = {
    appearance: {
        value: Appearance;
    };
    tema: {
        value: Tema;
    };
    resolvedAppearance: () => ResolvedAppearance;
    updateAppearance: (value: Appearance) => void;
    updateTema: (value: Tema) => void;
};

const tema = $state<{ value: Tema }>({ value: TEMA_POR_DEFECTO });
// El modo claro/oscuro se deriva del tema; se mantiene por compatibilidad con los componentes que lo leen.
const appearance = $state<{ value: Appearance }>({ value: 'dark' });

let themeChangeMediaQuery: MediaQueryList | null = null;

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return true;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

export function esTema(valor: unknown): valor is Tema {
    return (
        typeof valor === 'string' &&
        (TEMAS as readonly string[]).includes(valor)
    );
}

export function resolverTema(valor: Tema): TemaResuelto {
    if (valor === 'auto') {
        return prefersDark() ? 'terminal' : 'corporativo';
    }

    return valor;
}

function aparienciaDe(valor: Tema): Appearance {
    if (valor === 'auto') return 'system';

    return valor === 'corporativo' ? 'light' : 'dark';
}

const getResolvedAppearance = (): ResolvedAppearance => {
    return resolverTema(tema.value) === 'corporativo' ? 'light' : 'dark';
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const aplicarTema = (valor: Tema): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const resuelto = resolverTema(valor);
    const oscuro = resuelto !== 'corporativo';
    const raiz = document.documentElement;

    raiz.dataset.tema = resuelto;
    raiz.classList.toggle('dark', oscuro);
    raiz.style.colorScheme = oscuro ? 'dark' : 'light';
};

function leerStorage(clave: string): string | null {
    try {
        return localStorage.getItem(clave);
    } catch {
        return null;
    }
}

function escribirStorage(clave: string, valor: string): void {
    try {
        localStorage.setItem(clave, valor);
    } catch {
        // Navegación privada o almacenamiento bloqueado: la cookie basta.
    }
}

/**
 * Tema a usar al cargar: el que resolvió el servidor (el de la cuenta o la cookie) y,
 * si no llegó, el del navegador. Si solo había la apariencia antigua, se traduce.
 */
const temaGuardado = (): Tema => {
    const delServidor =
        typeof document !== 'undefined'
            ? document.documentElement.dataset.temaElegido
            : undefined;

    if (esTema(delServidor)) {
        return delServidor;
    }

    const guardado = leerStorage('tema');

    if (esTema(guardado)) {
        return guardado;
    }

    const antigua = leerStorage('appearance');

    if (antigua === 'light') return 'corporativo';
    if (antigua === 'system') return 'auto';

    return TEMA_POR_DEFECTO;
};

const handleSystemThemeChange = (): void => {
    aplicarTema(tema.value);
};

const detachThemeChangeListener = (): void => {
    if (!themeChangeMediaQuery) {
        return;
    }

    themeChangeMediaQuery.removeEventListener(
        'change',
        handleSystemThemeChange,
    );
    themeChangeMediaQuery = null;
};

export function initializeTheme(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    updateTema(temaGuardado());

    detachThemeChangeListener();
    themeChangeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    themeChangeMediaQuery.addEventListener('change', handleSystemThemeChange);

    return detachThemeChangeListener;
}

/** Aplica el tema y lo recuerda en este navegador (la cookie evita el parpadeo al cargar). */
export function updateTema(valor: Tema): void {
    tema.value = valor;
    appearance.value = aparienciaDe(valor);

    if (typeof window !== 'undefined') {
        escribirStorage('tema', valor);
        escribirStorage('appearance', appearance.value);
    }

    setCookie('tema', valor);
    setCookie('appearance', appearance.value);
    aplicarTema(valor);
}

/** Compatibilidad: cambiar solo claro/oscuro elige el tema equivalente. */
export function updateAppearance(value: Appearance): void {
    updateTema(
        value === 'light'
            ? 'corporativo'
            : value === 'system'
              ? 'auto'
              : 'terminal',
    );
}

export function themeState(): ThemeState {
    return {
        appearance,
        tema,
        resolvedAppearance: getResolvedAppearance,
        updateAppearance,
        updateTema,
    };
}
