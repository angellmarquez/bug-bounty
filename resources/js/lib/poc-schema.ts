import type { PocSchemaField } from '@/types/domain';

export function schemaVacio(schema: PocSchemaField[]): Record<string, unknown> {
    const data: Record<string, unknown> = {};
    for (const field of schema) {
        if (field.repeatable) {
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
        const value = data[field.name];
        if (field.required) {
            if (field.repeatable) {
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
            !field.repeatable &&
            field.type === 'url' &&
            value &&
            typeof value === 'string'
        ) {
            try {
                new URL(value);
            } catch {
                errors[field.name] = `${field.label} debe ser una URL válida`;
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
