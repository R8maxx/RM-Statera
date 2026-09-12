<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Lo que vence, repartido en las dos preguntas distintas que plantea.
 *
 * Una evidencia caducada es un incumplimiento hoy; una que vence dentro de tres
 * semanas es trabajo que planificar. Mezclarlas en una sola lista obliga a
 * mirarse las fechas una a una para saber si hay que correr.
 */
final readonly class Vencimientos
{
    /**
     * @param  list<EvidenciaQueVence>  $caducadas
     * @param  list<EvidenciaQueVence>  $porCaducar
     */
    public function __construct(
        public array $caducadas,
        public array $porCaducar,
        public int $dias,
    ) {}

    public function hayAlgo(): bool
    {
        return $this->caducadas !== [] || $this->porCaducar !== [];
    }

    public function total(): int
    {
        return count($this->caducadas) + count($this->porCaducar);
    }
}
