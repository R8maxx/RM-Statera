<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Excepciones;

use App\Domain\Contexto\Enums\EstadoAnalisis;
use DomainException;

/**
 * El análisis del contexto no se puede aprobar todavía.
 *
 * Tres motivos, y los tres se comprueban **en el dominio y no en el `FormRequest`**
 * porque valen igual para un importador o para el seeder. Los `CHECK` de la base
 * cubren dos de ellos, pero un `QueryException` sube como un 500 con un mensaje sin
 * tildes que no lee nadie; esto sube como error de validación al lado del campo.
 */
final class AnalisisNoAprobable extends DomainException
{
    private function __construct(
        public readonly string $motivo,
        public readonly ?string $campo = null,
    ) {
        parent::__construct($motivo);
    }

    public static function porEstado(EstadoAnalisis $estado): self
    {
        return new self(sprintf(
            'Sólo se aprueba el borrador, y este análisis está en «%s».',
            $estado->etiqueta(),
        ));
    }

    /**
     * La enmienda 1:2024 obliga a **determinar si** el cambio climático es una
     * cuestión pertinente. «No lo hemos mirado» y «lo hemos mirado y no aplica» no
     * pueden ser la misma fila.
     */
    public static function porElClima(): self
    {
        return new self(
            'Hay que declarar si el cambio climático es una cuestión pertinente, y razonarlo, antes de aprobar el análisis.',
            'clima_justificacion',
        );
    }

    /**
     * Un análisis sin ninguna cuestión no es un análisis del contexto: es un papel
     * firmado que dice que la organización no opera en ningún entorno.
     */
    public static function porEstarVacio(): self
    {
        return new self('Un análisis del contexto no se aprueba sin ninguna cuestión registrada.');
    }
}
