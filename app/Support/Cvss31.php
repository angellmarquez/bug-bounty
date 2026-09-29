<?php

namespace App\Support;

use App\Enums\Severidad;

/**
 * Calculadora de la puntuación base CVSS 3.1 en el servidor (misma fórmula que
 * resources/js/lib/cvss.ts). La severidad reparte los puntos de reputación, así que no se
 * acepta la que manda el navegador: se recalcula siempre a partir del vector.
 *
 * @see https://www.first.org/cvss/v3.1/specification-document
 */
final class Cvss31
{
    /** Vector base completo, en el orden de la especificación. */
    public const PATRON = '/^CVSS:3\.1\/AV:[NALP]\/AC:[LH]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH]$/';

    private const AV = ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2];

    private const AC = ['L' => 0.77, 'H' => 0.44];

    private const UI = ['N' => 0.85, 'R' => 0.62];

    private const CIA = ['N' => 0.0, 'L' => 0.22, 'H' => 0.56];

    public static function esValido(?string $vector): bool
    {
        return is_string($vector) && preg_match(self::PATRON, $vector) === 1;
    }

    /**
     * @return array{puntuacion: float, severidad: Severidad}|null null si el vector no es CVSS 3.1 válido
     */
    public static function calcular(?string $vector): ?array
    {
        if (! self::esValido($vector)) {
            return null;
        }

        $m = [];
        foreach (explode('/', substr((string) $vector, strlen('CVSS:3.1/'))) as $parte) {
            [$clave, $valor] = explode(':', $parte);
            $m[$clave] = $valor;
        }

        $alcanceCambia = $m['S'] === 'C';
        $pr = match ($m['PR']) {
            'N' => 0.85,
            'L' => $alcanceCambia ? 0.68 : 0.62,
            default => $alcanceCambia ? 0.5 : 0.27,
        };

        $explotabilidad = 8.22 * self::AV[$m['AV']] * self::AC[$m['AC']] * $pr * self::UI[$m['UI']];
        $iss = 1 - (1 - self::CIA[$m['C']]) * (1 - self::CIA[$m['I']]) * (1 - self::CIA[$m['A']]);
        $impacto = $alcanceCambia
            ? 7.52 * ($iss - 0.029) - 3.25 * (($iss - 0.02) ** 15)
            : 6.42 * $iss;

        $puntuacion = $impacto <= 0
            ? 0.0
            : self::redondearArriba(min(($alcanceCambia ? 1.08 : 1.0) * ($impacto + $explotabilidad), 10));

        return ['puntuacion' => $puntuacion, 'severidad' => self::severidad($puntuacion)];
    }

    public static function severidad(float $puntuacion): Severidad
    {
        return match (true) {
            $puntuacion == 0.0 => Severidad::Ninguna,
            $puntuacion < 4.0 => Severidad::Baja,
            $puntuacion < 7.0 => Severidad::Media,
            $puntuacion < 9.0 => Severidad::Alta,
            default => Severidad::Critica,
        };
    }

    /** "Roundup" de la especificación 3.1: el menor valor con un decimal que sea >= al dado. */
    private static function redondearArriba(float $valor): float
    {
        $entero = (int) round($valor * 100000);

        return $entero % 10000 === 0
            ? $entero / 100000
            : (floor($entero / 10000) + 1) / 10;
    }
}
