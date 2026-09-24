import type { PocSchemaField } from '@/types/domain';

/**
 * Todo programa exige PoC (ver AGENTS.md): comprueba que el investigador diga la
 * verdad. Cuando el programa no define campos propios, se usa este campo genérico
 * en su lugar para que igual quede una evidencia obligatoria.
 */
export const CAMPO_POC_POR_DEFECTO: PocSchemaField = {
    name: 'evidencia',
    label: 'Evidencia y pasos para reproducir',
    type: 'textarea',
    required: true,
    help: 'Describe paso a paso cómo reproducir la vulnerabilidad (o pega el payload/PoC).',
};

export function esBooleanoVerdadero(val: unknown): boolean {
    return val === true || val === 1 || val === '1' || val === 'true';
}

export function schemaEfectivo(
    schema: PocSchemaField[] | null | undefined,
): PocSchemaField[] {
    if (!schema || schema.length === 0) {
        return [CAMPO_POC_POR_DEFECTO];
    }
    return schema.map((field) => ({
        ...field,
        required: esBooleanoVerdadero(field.required),
        repeatable: esBooleanoVerdadero(field.repeatable),
    }));
}

export function schemaVacio(schema: PocSchemaField[]): Record<string, unknown> {
    const data: Record<string, unknown> = {};
    for (const field of schema) {
        if (esBooleanoVerdadero(field.repeatable)) {
            data[field.name] = [field.defaultValue ?? ''];
        } else {
            data[field.name] = field.defaultValue ?? '';
        }
    }
    return data;
}

export function validarPoc(
    data: Record<string, unknown>,
    schema: PocSchemaField[],
): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const field of schema) {
        const isRequired = esBooleanoVerdadero(field.required);
        const isRepeatable = esBooleanoVerdadero(field.repeatable);
        const value = data[field.name];
        if (isRequired) {
            if (isRepeatable) {
                const items = (Array.isArray(value) ? value : []) as string[];
                if (items.length === 0) {
                    errors[field.name] =
                        `${field.label} requiere al menos un elemento`;
                } else {
                    items.forEach((item, i) => {
                        if (!item || item.trim() === '') {
                            errors[`${field.name}.${i}`] =
                                `${field.label} #${i + 1} no puede estar vacio`;
                        }
                    });
                }
            } else if (value === undefined || value === null || value === '') {
                errors[field.name] = `${field.label} es obligatorio`;
            }
        }
        if (
            !isRepeatable &&
            field.type === 'url' &&
            value &&
            typeof value === 'string' &&
            value.trim() !== ''
        ) {
            try {
                new URL(value);
            } catch {
                errors[field.name] =
                    `${field.label} debe ser una URL válida (ej. https://...)`;
            }
        }
    }
    return errors;
}

export function formatearPocMarkdown(
    data: Record<string, unknown>,
    schema: PocSchemaField[],
): string {
    let md = '## Prueba de Concepto\n\n';
    for (const field of schema) {
        const value = data[field.name];
        md += `### ${field.label}\n\n`;
        if (field.type === 'code' || field.type === 'textarea') {
            md += '```\n' + ((value as string) ?? '') + '\n```\n\n';
        } else if (field.repeatable && Array.isArray(value)) {
            value.forEach((item, i) => {
                md += `${i + 1}. ${item}\n`;
            });
            md += '\n';
        } else {
            md += `${(value as string) ?? 'N/A'}\n\n`;
        }
    }
    return md;
}

export function esquemasIguales(
    data: Record<string, unknown>,
    original: Record<string, unknown>,
): boolean {
    return JSON.stringify(data) === JSON.stringify(original);
}
