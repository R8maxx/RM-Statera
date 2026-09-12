<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Lo que vence, repartido en las preguntas distintas que plantea.
 *
 * Dos ejes. Por un lado, **evidencias y tareas van aparte**: una evidencia
 * caducada es una prueba que ya no prueba y una tarea vencida es trabajo que no
 * se hizo; se arreglan de formas distintas y las lleva gente distinta. Por otro,
 * dentro de cada una, **lo pasado y lo inminente**: lo primero es un
 * incumplimiento hoy y lo segundo es algo que planificar, y mezclarlos obliga a
 * mirarse las fechas una a una para saber si hay que correr.
 */
final readonly class Vencimientos
{
    /**
     * @param  list<Vencimiento>  $evidenciasCaducadas
     * @param  list<Vencimiento>  $evidenciasPorCaducar
     * @param  list<Vencimiento>  $tareasVencidas
     * @param  list<Vencimiento>  $tareasPorVencer
     */
    public function __construct(
        public array $evidenciasCaducadas,
        public array $evidenciasPorCaducar,
        public array $tareasVencidas,
        public array $tareasPorVencer,
        public int $dias,
    ) {}

    public function hayAlgo(): bool
    {
        return $this->total() > 0;
    }

    /** Lo que ya se pasó: es lo que decide el asunto del correo. */
    public function pasados(): int
    {
        return count($this->evidenciasCaducadas) + count($this->tareasVencidas);
    }

    public function total(): int
    {
        return $this->pasados() + count($this->evidenciasPorCaducar) + count($this->tareasPorVencer);
    }
}
