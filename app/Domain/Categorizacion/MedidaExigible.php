<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Categorizacion\Enums\OrigenExigencia;

/**
 * Una medida que el sistema le exige a la organización, con el nivel exigido y
 * el motivo por el que se exige.
 *
 * Del punto 3 en adelante, cada una de éstas se convierte en una fila de
 * `implantaciones` con su `exigencia_calculada`.
 */
final readonly class MedidaExigible
{
    public function __construct(
        public int $requisitoId,
        public string $codigo,
        public string $titulo,
        public Exigencia $exigencia,
        public OrigenExigencia $origen,
        public ?Dimension $dimensionModuladora = null,
    ) {}

    public function nivelRefuerzo(): ?int
    {
        return $this->exigencia->nivelRefuerzo();
    }

    /** Primer segmento del código: `org`, `op` o `mp`. */
    public function familia(): string
    {
        return explode('.', $this->codigo)[0];
    }

    public function con(Exigencia $exigencia, OrigenExigencia $origen): self
    {
        return new self(
            $this->requisitoId,
            $this->codigo,
            $this->titulo,
            $exigencia,
            $origen,
            $this->dimensionModuladora,
        );
    }
}
