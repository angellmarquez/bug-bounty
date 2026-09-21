import type { PocSchemaField } from '@/types/domain';

export type EntradaPoc = {
    clave: string;
    etiqueta: string;
    tipo: string;
    valores: string[];
};

function comoTexto(valor: unknown): string {
    if (valor === null || valor === undefined) return '';
    if (typeof valor === 'string') return valor;
    if (typeof valor === 'number' || typeof valor === 'boolean')
        return String(valor);
    return JSON.stringify(valor, null, 2);
}

/**
 * Convierte la PoC enviada en filas legibles: primero los campos del formulario
 * del programa (con su etiqueta, en su orden) y después cualquier dato extra.
 */
export function entradasPoc(
    poc: Record<string, unknown> | null | undefined,
    schema: PocSchemaField[] | null | undefined,
): EntradaPoc[] {
    if (!poc) return [];

    const usadas = new Set<string>();
    const entradas: EntradaPoc[] = [];

    for (const campo of schema ?? []) {
        if (!(campo.name in poc)) continue;
        usadas.add(campo.name);

        const crudo = poc[campo.name];
        const valores = (Array.isArray(crudo) ? crudo : [crudo])
            .map((valor) => {
                const texto = comoTexto(valor);
                // En un campo de selección se muestra la etiqueta de la opción elegida.
                return (
                    campo.options?.find((opcion) => opcion.value === texto)
                        ?.label ?? texto
                );
            })
            .filter((valor) => valor !== '');

        if (valores.length > 0) {
            entradas.push({
                clave: campo.name,
                etiqueta: campo.label,
                tipo: campo.type,
                valores,
            });
        }
    }

    for (const [clave, crudo] of Object.entries(poc)) {
        if (usadas.has(clave)) continue;

        const valores = (Array.isArray(crudo) ? crudo : [crudo])
            .map(comoTexto)
            .filter((valor) => valor !== '');
        if (valores.length > 0) {
            entradas.push({ clave, etiqueta: clave, tipo: 'text', valores });
        }
    }

    return entradas;
}

/** Solo se enlazan URLs http(s): un valor enviado por el investigador nunca debe ser un `javascript:`. */
export function esUrlSegura(valor: string): boolean {
    return /^https?:\/\//i.test(valor);
}
