<?php

namespace App\Services\Reportes;

use App\Models\Reporte;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ayuda a moderación a encontrar el original de un informe duplicado.
 *
 * Solo compara columnas en claro (título, categoría y vector CVSS): la descripción y
 * la PoC van cifradas. Devuelve una ficha comparativa de cada candidato, sin datos del autor
 * (el triaje es ciego) ni el contenido del informe, que el moderador no puede abrir.
 *
 * Un candidato es un informe anterior del mismo programa que no esté descartado: un informe
 * rechazado, fuera de alcance o ya duplicado no vuelve duplicado al nuevo.
 */
class DetectorDuplicados
{
    /** Estados que invalidan a un informe como original. */
    public const ESTADOS_NO_ORIGINAL = ['borrador', 'rechazado', 'duplicado', 'fuera_de_alcance'];

    /** A partir de esta puntuación el candidato se sugiere como posible duplicado. */
    public const UMBRAL_SUGERENCIA = 45;

    private const TERMINOS_SEGURIDAD = [
        'sql', 'sqli', 'xss', 'csrf', 'ssrf', 'idor', 'rce', 'lfi', 'rfi', 'xxe', 'ssti', 'jwt',
        'oauth', 'cors', 'bypass', 'inyeccion', 'injection', 'deserializacion', 'deserialization',
        'traversal', 'redirect', 'upload', 'takeover', 'clickjacking',
    ];

    private const PALABRAS_VACIAS = [
        'the', 'and', 'for', 'with', 'del', 'las', 'los', 'una', 'uno', 'por', 'para', 'con', 'sin',
        'que', 'desde', 'sobre', 'endpoint', 'parametro', 'pagina', 'vulnerabilidad',
    ];

    /**
     * Candidatos a original de `$reporte`, los sugeridos primero y por puntuación.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidatos(Reporte $reporte, int $limite = 100): Collection
    {
        return Reporte::query()
            ->where('programa_id', $reporte->programa_id)
            ->whereKeyNot($reporte->id)
            ->whereNotIn('estado', self::ESTADOS_NO_ORIGINAL)
            ->whereRaw('COALESCE(enviado_en, created_at) <= ?', [ColaDeValidacion::prioridad($reporte)])
            ->orderByRaw('COALESCE(enviado_en, created_at) asc')
            ->orderBy('id')
            ->limit($limite)
            ->get(['id', 'numero_reporte', 'titulo', 'categoria', 'severidad', 'puntuacion_cvss', 'vector_cvss', 'estado', 'enviado_en', 'created_at'])
            ->filter(fn (Reporte $candidato): bool => ColaDeValidacion::llegoAntes($candidato, $reporte))
            ->map(fn (Reporte $candidato): array => $this->ficha($candidato, $reporte))
            ->sort(fn (array $a, array $b): int => [$b['sugerido'], $b['aprobado'], $b['puntuacion']] <=> [$a['sugerido'], $a['aprobado'], $a['puntuacion']])
            ->values();
    }

    /**
     * Ficha comparativa: lo necesario para comparar, nada del autor ni del contenido cifrado.
     *
     * @return array<string, mixed>
     */
    public function ficha(Reporte $candidato, Reporte $reporte): array
    {
        ['puntuacion' => $puntuacion, 'motivos' => $motivos] = $this->comparar($candidato, $reporte);

        return [
            'id' => $candidato->id,
            'numero_reporte' => $candidato->numero_reporte,
            'titulo' => $candidato->titulo,
            'categoria' => $candidato->categoria,
            'severidad' => $candidato->severidad?->value,
            'puntuacion_cvss' => $candidato->puntuacion_cvss === null ? null : (float) $candidato->puntuacion_cvss,
            'vector_cvss' => $candidato->vector_cvss,
            'estado' => $candidato->estado->value,
            'aprobado' => in_array($candidato->estado->value, Reporte::ESTADOS_APROBADOS, true),
            'enviado_en' => ($candidato->enviado_en ?? $candidato->created_at)?->toISOString(),
            'puntuacion' => $puntuacion,
            'motivos' => $motivos,
            'sugerido' => $puntuacion >= self::UMBRAL_SUGERENCIA,
        ];
    }

    /**
     * Puntúa el parecido de dos informes (0-100) y explica por qué.
     * Coincidir solo en categoría o severidad no basta para sugerirlo: en un programa hay
     * muchos informes de la misma categoría.
     *
     * @return array{puntuacion: int, motivos: array<int, string>}
     */
    public function comparar(Reporte $a, Reporte $b): array
    {
        $puntuacion = 0;
        $motivos = [];

        $palabrasA = $this->palabras((string) $a->titulo);
        $palabrasB = $this->palabras((string) $b->titulo);
        $comunes = array_values(array_intersect($palabrasA, $palabrasB));
        $union = array_unique([...$palabrasA, ...$palabrasB]);

        if ($comunes !== [] && $union !== []) {
            $parecido = count($comunes) / count($union);
            $puntuacion += (int) round($parecido * 40);

            $terminos = array_values(array_intersect($comunes, self::TERMINOS_SEGURIDAD));
            if ($terminos !== []) {
                $puntuacion += 20;
                $motivos[] = 'Título con el mismo tipo de fallo ('.implode(', ', $terminos).')';
            } elseif ($parecido >= 0.3) {
                $motivos[] = 'Título parecido';
            }
        }

        if ($this->normalizar((string) $a->categoria) !== '' && $this->normalizar((string) $a->categoria) === $this->normalizar((string) $b->categoria)) {
            $puntuacion += 20;
            $motivos[] = 'Misma categoría';
        }

        if ($a->vector_cvss !== null && $a->vector_cvss === $b->vector_cvss) {
            $puntuacion += 20;
            $motivos[] = 'Mismo vector CVSS';
        }

        return ['puntuacion' => min(100, $puntuacion), 'motivos' => $motivos];
    }

    /**
     * Palabras significativas del título, sin acentos ni mayúsculas.
     *
     * @return array<int, string>
     */
    private function palabras(string $texto): array
    {
        $partes = preg_split('/[^a-z0-9]+/', $this->normalizar($texto)) ?: [];

        return array_values(array_unique(array_filter(
            $partes,
            fn (string $palabra): bool => (mb_strlen($palabra) >= 3 || in_array($palabra, self::TERMINOS_SEGURIDAD, true))
                && ! in_array($palabra, self::PALABRAS_VACIAS, true),
        )));
    }

    private function normalizar(string $texto): string
    {
        return trim(Str::lower(Str::ascii($texto)));
    }
}
