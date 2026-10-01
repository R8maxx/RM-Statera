<?php

declare(strict_types=1);

namespace App\Domain\Sistema\Excepciones;

use DomainException;

/**
 * Un perfil de cumplimiento que no se puede asignar a este sistema.
 *
 * **El vacío es el caso que importa.** El paso 4 del motor deja fuera todo lo que
 * no está en el perfil, así que un perfil sin medidas pondría el sistema entero
 * en «no aplica» — un sistema que no cumple nada y que la herramienta enseñaría
 * como al día. Es exactamente el fallo silencioso que encabeza la lista de
 * prioridades de cobertura, y por eso se impide aquí y no sólo en el formulario.
 */
final class PerfilNoAplicable extends DomainException
{
    public static function sinContenido(string $codigo): self
    {
        return new self(
            "El perfil {$codigo} no lleva ninguna medida cargada: asignarlo dejaría el sistema sin nada exigible. "
            .'Sus medidas se cargan en el catálogo cuando estén contrastadas con la guía CCN-STIC.',
        );
    }

    public static function deOtroMarco(string $codigo): self
    {
        return new self("El perfil {$codigo} es de otro marco normativo que el del sistema.");
    }
}
