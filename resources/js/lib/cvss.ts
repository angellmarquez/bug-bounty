import type { Severidad } from '@/types/enums';

export type CvssMetricValue = {
    id: string;
    label: string;
    weight: number;
};

export type CvssMetric = {
    id: string;
    label: string;
    abbrev: string;
    values: CvssMetricValue[];
};

export const CVSS_METRICS: CvssMetric[] = [
    {
        id: 'AV',
        label: 'Attack Vector',
        abbrev: 'AV',
        values: [
            { id: 'N', label: 'Network', weight: 0.85 },
            { id: 'A', label: 'Adjacent', weight: 0.62 },
            { id: 'L', label: 'Local', weight: 0.55 },
            { id: 'P', label: 'Physical', weight: 0.2 },
        ],
    },
    {
        id: 'AC',
        label: 'Attack Complexity',
        abbrev: 'AC',
        values: [
            { id: 'L', label: 'Low', weight: 0.77 },
            { id: 'H', label: 'High', weight: 0.44 },
        ],
    },
    {
        id: 'PR',
        label: 'Privileges Required',
        abbrev: 'PR',
        values: [
            { id: 'N', label: 'None', weight: 0.85 },
            { id: 'L', label: 'Low', weight: 0.62 },
            { id: 'H', label: 'High', weight: 0.27 },
        ],
    },
    {
        id: 'UI',
        label: 'User Interaction',
        abbrev: 'UI',
        values: [
            { id: 'N', label: 'None', weight: 0.85 },
            { id: 'R', label: 'Required', weight: 0.62 },
        ],
    },
    {
        id: 'S',
        label: 'Scope',
        abbrev: 'S',
        values: [
            { id: 'U', label: 'Unchanged', weight: 0.0 },
            { id: 'C', label: 'Changed', weight: 0.0 },
        ],
    },
    {
        id: 'C',
        label: 'Confidentiality',
        abbrev: 'C',
        values: [
            { id: 'N', label: 'None', weight: 0.0 },
            { id: 'L', label: 'Low', weight: 0.22 },
            { id: 'H', label: 'High', weight: 0.56 },
        ],
    },
    {
        id: 'I',
        label: 'Integrity',
        abbrev: 'I',
        values: [
            { id: 'N', label: 'None', weight: 0.0 },
            { id: 'L', label: 'Low', weight: 0.22 },
            { id: 'H', label: 'High', weight: 0.56 },
        ],
    },
    {
        id: 'A',
        label: 'Availability',
        abbrev: 'A',
        values: [
            { id: 'N', label: 'None', weight: 0.0 },
            { id: 'L', label: 'Low', weight: 0.22 },
            { id: 'H', label: 'High', weight: 0.56 },
        ],
    },
];

export function parseCvssVector(vector: string): Record<string, string> {
    const metrics: Record<string, string> = {};
    const cleaned = vector.replace('CVSS:3.1/', '');
    for (const part of cleaned.split('/')) {
        const [key, value] = part.split(':');
        if (key && value) {
            metrics[key] = value;
        }
    }
    return metrics;
}

export function buildCvssVector(metrics: Record<string, string>): string {
    const parts = CVSS_METRICS.map(
        (m) => `${m.abbrev}:${metrics[m.id] ?? 'N'}`,
    );
    return `CVSS:3.1/${parts.join('/')}`;
}

function roundUp(value: number): number {
    const int = Math.round(value * 100000);
    if (int % 10000 === 0) {
        return int / 100000;
    }
    return (Math.floor(int / 10000) + 1) / 10;
}

export function calcularPuntuacionBase(
    metrics: Record<string, string>,
): number {
    const av =
        CVSS_METRICS[0].values.find((v) => v.id === (metrics.AV ?? 'N'))
            ?.weight ?? 0.85;
    const ac =
        CVSS_METRICS[1].values.find((v) => v.id === (metrics.AC ?? 'L'))
            ?.weight ?? 0.77;
    const prValue =
        metrics.PR === 'N' ? 0.85 : metrics.PR === 'L' ? 0.62 : 0.27;
    const pr =
        metrics.S === 'C'
            ? prValue
            : metrics.PR === 'N'
              ? 0.85
              : metrics.PR === 'L'
                ? 0.62
                : 0.27;
    const ui =
        CVSS_METRICS[3].values.find((v) => v.id === (metrics.UI ?? 'N'))
            ?.weight ?? 0.85;

    const exploitability = 8.22 * av * ac * pr * ui;

    const c =
        CVSS_METRICS[5].values.find((v) => v.id === (metrics.C ?? 'N'))
            ?.weight ?? 0.0;
    const i =
        CVSS_METRICS[6].values.find((v) => v.id === (metrics.I ?? 'N'))
            ?.weight ?? 0.0;
    const a =
        CVSS_METRICS[7].values.find((v) => v.id === (metrics.A ?? 'N'))
            ?.weight ?? 0.0;

    const iss = 1 - (1 - c) * (1 - i) * (1 - a);

    let impact: number;
    if (metrics.S === 'C') {
        impact = 7.52 * (iss - 0.029) - 3.25 * Math.pow(iss - 0.02, 15);
    } else {
        impact = 6.42 * iss;
    }

    if (impact <= 0) {
        return 0;
    }

    let baseScore: number;
    if (metrics.S === 'C') {
        baseScore = roundUp(Math.min(1.08 * (impact + exploitability), 10));
    } else {
        baseScore = roundUp(Math.min(impact + exploitability, 10));
    }

    return baseScore;
}

export function severidadDePuntuacion(score: number): Severidad {
    if (score === 0.0) return 'ninguna';
    if (score >= 0.1 && score <= 3.9) return 'baja';
    if (score >= 4.0 && score <= 6.9) return 'media';
    if (score >= 7.0 && score <= 8.9) return 'alta';
    return 'critica';
}
