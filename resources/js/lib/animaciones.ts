// Animaciones con propósito: cortas y desactivadas si el sistema pide menos movimiento.

export function prefiereMenosMovimiento(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * Acción `use:aparecer`: el elemento aparece (sube y se funde) al entrar en pantalla.
 * `retraso` en ms permite escalonar tarjetas en cascada.
 */
export function aparecer(
    nodo: HTMLElement,
    retraso = 0,
): { destroy: () => void } {
    if (
        prefiereMenosMovimiento() ||
        typeof IntersectionObserver === 'undefined'
    ) {
        return { destroy: () => {} };
    }

    // El estado oculto es una clase (ver app.css): al imprimir se anula y el contenido nunca se pierde.
    nodo.style.setProperty('--aparecer-retraso', `${retraso}ms`);
    nodo.classList.add('aparecer', 'aparecer-oculto');

    const revelar = () => {
        nodo.classList.remove('aparecer-oculto');
        observador.disconnect();
        window.removeEventListener('scroll', alDesplazar);
    };

    // Si un salto (ancla, scroll rápido) deja el elemento por encima de la pantalla, también se revela.
    const alDesplazar = () => {
        if (nodo.getBoundingClientRect().top < window.innerHeight) revelar();
    };

    const observador = new IntersectionObserver(
        (entradas) => {
            if (
                entradas.some(
                    (e) => e.isIntersecting || e.boundingClientRect.top < 0,
                )
            )
                revelar();
        },
        { threshold: 0.1 },
    );

    observador.observe(nodo);
    window.addEventListener('scroll', alDesplazar, { passive: true });

    return { destroy: revelar };
}

/**
 * Acción `use:contador={valor}`: el número sube desde 0 hasta `valor` al verse.
 */
export function contador(
    nodo: HTMLElement,
    valor: number,
): { update: (nuevo: number) => void; destroy: () => void } {
    const formato = new Intl.NumberFormat('es-ES');
    let observador: IntersectionObserver | null = null;
    let cuadro = 0;

    const pintar = (n: number) => {
        nodo.textContent = formato.format(n);
    };

    const animar = (objetivo: number) => {
        if (prefiereMenosMovimiento() || objetivo <= 0) {
            pintar(objetivo);

            return;
        }

        const inicio = performance.now();
        const duracion = 1100;
        const paso = (ahora: number) => {
            const t = Math.min(1, (ahora - inicio) / duracion);
            pintar(Math.round(objetivo * (1 - Math.pow(1 - t, 3))));
            if (t < 1) cuadro = requestAnimationFrame(paso);
        };
        cuadro = requestAnimationFrame(paso);
    };

    const iniciar = (objetivo: number) => {
        observador?.disconnect();
        if (typeof IntersectionObserver === 'undefined') {
            pintar(objetivo);

            return;
        }
        pintar(0);
        observador = new IntersectionObserver((entradas) => {
            if (entradas.some((e) => e.isIntersecting)) {
                observador?.disconnect();
                animar(objetivo);
            }
        });
        observador.observe(nodo);
    };

    iniciar(valor);

    return {
        update: (nuevo: number) => iniciar(nuevo),
        destroy: () => {
            observador?.disconnect();
            cancelAnimationFrame(cuadro);
        },
    };
}
