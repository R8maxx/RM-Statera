<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Catalogo\Enums\CategoriaEns;

/**
 * Lo que `RequisitosDeDeclaracion` contesta sobre un sistema.
 *
 * La autoevaluación puede venir aunque haya bloqueos —la ficha la enseña para que
 * se vea de dónde salen—; sólo cuando `bloqueos` está vacío se puede declarar.
 */
final readonly class ComprobacionPrevia
{
    /**
     * @param  list<string>  $bloqueos
     */
    public function __construct(
        public ?Auditoria $autoevaluacion,
        public ?CategoriaEns $categoria,
        public array $bloqueos,
    ) {}

    public function sePuedeIniciar(): bool
    {
        return $this->bloqueos === [] && $this->autoevaluacion !== null && $this->categoria !== null;
    }
}
