<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use Illuminate\Support\Carbon;

/**
 * Cada cuánto hay que volver a hacer algo, en meses.
 *
 * **Un entero y no un enum**, que es lo que ya hacen
 * `documentos.periodicidad_revision_meses` y
 * `metodologias_riesgo.periodicidad_revision_meses`. El producto tiene ya dos
 * enums de periodicidad —`Metrica\Enums\Periodicidad` y
 * `Evidencia\Enums\PeriodicidadRenovacion`— y ninguno de los dos sabe decir
 * «bienal», que es exactamente la cadencia con la que se renueva la conformidad
 * del ENS. Añadir un tercero para meter un caso más es cómo se acaba con tres
 * listas que casi coinciden.
 *
 * Esta clase es lo único que el entero necesitaba: un nombre legible y la
 * aritmética de sumarlo a una fecha, escrita una vez.
 */
final readonly class Cadencia
{
    public const MINIMO_MESES = 1;

    public const MAXIMO_MESES = 120;

    public function __construct(public int $meses) {}

    /**
     * «Anual», «Bienal», «Trienal», y «cada 18 meses» para lo que no tiene
     * nombre propio.
     *
     * Los tres nombres están porque son los que usa el sector —un auditor dice
     * «la bienal», no «la de veinticuatro meses»—; el resto se dice con el
     * número, que es más claro que inventarle un adjetivo a dieciocho meses.
     */
    public function etiqueta(): string
    {
        return match ($this->meses) {
            1 => 'Mensual',
            3 => 'Trimestral',
            6 => 'Semestral',
            12 => 'Anual',
            24 => 'Bienal',
            36 => 'Trienal',
            default => "Cada {$this->meses} meses",
        };
    }

    /**
     * La fecha desde la que se cuenta, más una cadencia.
     *
     * Lo resuelve Carbon y no aritmética a mano: sumar un mes al 31 de enero no
     * da el 31 de febrero, y esa es justo la clase de fallo que se ve en marzo.
     */
    public function despuesDe(Carbon $fecha): Carbon
    {
        return $fecha->copy()->startOfDay()->addMonthsNoOverflow($this->meses);
    }

    public function __toString(): string
    {
        return $this->etiqueta();
    }
}
