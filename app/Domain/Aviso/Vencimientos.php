<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Lo que vence, repartido en las preguntas distintas que plantea.
 *
 * Dos ejes. Por un lado, **cada fuente va aparte**: una evidencia caducada es una
 * prueba que ya no prueba, una tarea vencida es trabajo que no se hizo y una
 * revisión documental vencida es un documento que nadie ha vuelto a mirar desde
 * que se firmó. Se arreglan de formas distintas y las lleva gente distinta —la
 * última, quien tiene potestad para aprobar—. Por otro, dentro de cada una, **lo
 * pasado y lo inminente**: lo primero es un incumplimiento hoy y lo segundo es
 * algo que planificar, y mezclarlos obliga a mirarse las fechas una a una para
 * saber si hay que correr.
 */
final readonly class Vencimientos
{
    /**
     * @param  list<Vencimiento>  $evidenciasCaducadas
     * @param  list<Vencimiento>  $evidenciasPorCaducar
     * @param  list<Vencimiento>  $tareasVencidas
     * @param  list<Vencimiento>  $tareasPorVencer
     * @param  list<Vencimiento>  $documentosRevisionVencida
     * @param  list<Vencimiento>  $documentosPorRevisar
     */
    public function __construct(
        public array $evidenciasCaducadas,
        public array $evidenciasPorCaducar,
        public array $tareasVencidas,
        public array $tareasPorVencer,
        public array $documentosRevisionVencida,
        public array $documentosPorRevisar,
        public int $dias,
    ) {}

    public function hayAlgo(): bool
    {
        return $this->total() > 0;
    }

    /** Lo que ya se pasó: es lo que decide el asunto del correo. */
    public function pasados(): int
    {
        return count($this->evidenciasCaducadas)
            + count($this->tareasVencidas)
            + count($this->documentosRevisionVencida);
    }

    public function total(): int
    {
        return $this->pasados()
            + count($this->evidenciasPorCaducar)
            + count($this->tareasPorVencer)
            + count($this->documentosPorRevisar);
    }
}
