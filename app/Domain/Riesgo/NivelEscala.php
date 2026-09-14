<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

/**
 * Un escalón de una escala de la metodología: «3 — Media».
 *
 * El número es lo que multiplica y la etiqueta es lo que lee una persona. Van
 * juntos porque separarlos es cómo se acaba con una tabla llena de treses que
 * nadie sabe si son de cinco o de diez.
 */
final readonly class NivelEscala
{
    public function __construct(
        public int $valor,
        public string $etiqueta,
        public ?string $descripcion = null,
    ) {}

    /** @return array{valor: int, etiqueta: string, descripcion: string|null} */
    public function aArray(): array
    {
        return [
            'valor' => $this->valor,
            'etiqueta' => $this->etiqueta,
            'descripcion' => $this->descripcion,
        ];
    }
}
