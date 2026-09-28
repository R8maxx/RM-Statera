<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use Illuminate\Support\Carbon;

/**
 * Lo que se sabe de una copia sin descifrarla: cuándo se hizo, qué huella tiene
 * el fichero cifrado y cuántas filas tenía cada tabla **en el mismo instante
 * del volcado**.
 *
 * Esos recuentos son el patrón contra el que se mide la restauración. Salen de
 * la misma instantánea que usa `pg_dump`, así que no hay escrituras entre medias
 * que los desajusten: si al restaurar no cuadran, lo que falla es la copia.
 */
final readonly class Manifiesto
{
    /**
     * @param  array<string, int>  $recuentos
     */
    public function __construct(
        public string $nombre,
        public Carbon $hecha_en,
        public string $huella,
        public int $bytes,
        public array $recuentos,
    ) {}

    /**
     * @return array{nombre: string, hecha_en: string, huella: string, bytes: int, recuentos: array<string, int>}
     */
    public function aArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'hecha_en' => $this->hecha_en->toIso8601String(),
            'huella' => $this->huella,
            'bytes' => $this->bytes,
            'recuentos' => $this->recuentos,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        /** @var array<string, int> $recuentos */
        $recuentos = array_map('intval', (array) ($datos['recuentos'] ?? []));

        return new self(
            nombre: (string) $datos['nombre'],
            hecha_en: Carbon::parse((string) $datos['hecha_en']),
            huella: (string) $datos['huella'],
            bytes: (int) $datos['bytes'],
            recuentos: $recuentos,
        );
    }
}
