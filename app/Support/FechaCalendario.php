<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fechas de calendario (sin hora) como las elige una persona en un formulario, con usuarios
 * en cualquier zona horaria. La app trabaja en UTC: a las 21:00 en Caracas (UTC-4) en UTC ya
 * es el día siguiente, y "hoy" para esa persona parecería una fecha pasada.
 *
 * Por eso un día cuenta mientras sea ese día en algún lugar del mundo:
 * empieza cuando comienza en la zona más adelantada (UTC+14) y termina cuando acaba en la
 * más atrasada (UTC-12). Así nadie ve rechazado su propio "hoy".
 */
final class FechaCalendario
{
    /** UTC+14: donde cada día empieza primero. */
    public const ZONA_MAS_ADELANTADA = 'Pacific/Kiritimati';

    /** UTC-12: donde cada día termina último (la notación POSIX invierte el signo). */
    public const ZONA_MAS_ATRASADA = 'Etc/GMT+12';

    /** El día más antiguo que todavía es "hoy" en algún lugar (yyyy-mm-dd). */
    public static function hoyEnAlgunLugar(): string
    {
        return CarbonImmutable::now(self::ZONA_MAS_ATRASADA)->toDateString();
    }

    /** ¿Ese día ya pasó en todas partes? */
    public static function esPasada(CarbonInterface|string $fecha): bool
    {
        return self::dia($fecha) < self::hoyEnAlgunLugar();
    }

    /** ¿Ese día ya empezó en algún lugar? */
    public static function empezo(CarbonInterface|string $fecha): bool
    {
        return CarbonImmutable::parse(self::dia($fecha), self::ZONA_MAS_ADELANTADA)->startOfDay()->isPast();
    }

    /** ¿Ese día ya terminó en todas partes? */
    public static function termino(CarbonInterface|string $fecha): bool
    {
        return CarbonImmutable::parse(self::dia($fecha), self::ZONA_MAS_ATRASADA)->endOfDay()->isPast();
    }

    private static function dia(CarbonInterface|string $fecha): string
    {
        return $fecha instanceof CarbonInterface ? $fecha->toDateString() : CarbonImmutable::parse($fecha)->toDateString();
    }
}
