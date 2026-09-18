<?php

namespace App\Services\Pgp\DataObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Metadatos de una clave PGP (resultado de importar o generar).
 */
final readonly class PgpKeyInfo
{
    public function __construct(
        public string $fingerprint,
        public ?string $idClave = null,
        public ?string $algoritmo = null,
        public ?int $bits = null,
        public ?CarbonInterface $creadaEn = null,
        public ?CarbonInterface $expiraEn = null,
    ) {}

    /**
     * Crea una instancia desde un array asociativo con claves humanas.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fingerprint: (string) $data['fingerprint'],
            idClave: isset($data['id_clave']) ? (string) $data['id_clave'] : null,
            algoritmo: isset($data['algoritmo']) ? (string) $data['algoritmo'] : null,
            bits: isset($data['bits']) ? (int) $data['bits'] : null,
            creadaEn: isset($data['creada_en']) ? self::castDate($data['creada_en']) : null,
            expiraEn: isset($data['expira_en']) ? self::castDate($data['expira_en']) : null,
        );
    }

    /**
     * Devuelve la información como array asociativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'id_clave' => $this->idClave,
            'algoritmo' => $this->algoritmo,
            'bits' => $this->bits,
            'creada_en' => $this->creadaEn?->toDateString(),
            'expira_en' => $this->expiraEn?->toDateTimeString(),
        ];
    }

    private static function castDate(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : Carbon::parse($value);
    }
}
