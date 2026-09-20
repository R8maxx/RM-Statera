<?php

declare(strict_types=1);

namespace App\Domain\Persona\Excepciones;

use DomainException;

/**
 * Nombrar a quien ya no está en plantilla.
 *
 * Parece un detalle y no lo es: un rol ENS vigente sobre alguien que se fue es
 * exactamente el hallazgo que la gestión del personal busca, y dejarlo entrar
 * convertiría este registro en algo que hay que auditar aparte.
 */
final class PersonaNoDesignable extends DomainException
{
    public static function porEstarDeBaja(string $persona): self
    {
        return new self(sprintf(
            '%s ya no está en plantilla: un nombramiento vigente sobre quien se fue es justo el '
            .'hallazgo que la gestión del personal busca.',
            $persona,
        ));
    }
}
