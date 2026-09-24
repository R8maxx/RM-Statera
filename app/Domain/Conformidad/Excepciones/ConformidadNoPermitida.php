<?php

declare(strict_types=1);

namespace App\Domain\Conformidad\Excepciones;

use App\Domain\Conformidad\Enums\EstadoConformidad;
use DomainException;

/**
 * Un paso del § 4.17 que no se puede dar, con el motivo en castellano.
 *
 * **Una sola excepción y no una por caso**, porque el controlador hace lo mismo
 * con todas —devolver el mensaje al campo— y lo que importa es que el mensaje
 * diga qué falta. Cada constructor con nombre es un bloqueo distinto, y la ficha
 * enseña los mismos textos antes de que nadie pulse el botón
 * (`RequisitosDeDeclaracion`).
 */
final class ConformidadNoPermitida extends DomainException
{
    public static function transicion(EstadoConformidad $desde, EstadoConformidad $hasta): self
    {
        return new self("No se permite pasar de «{$desde->etiqueta()}» a «{$hasta->etiqueta()}».");
    }

    /**
     * Media y alta se certifican, y esa vía se modela pero no se implementa.
     */
    public static function viaNoImplementada(): self
    {
        return new self(
            'Un sistema de categoría media o alta no se declara: se certifica, con una auditoría de una '
            .'entidad acreditada por ENAC. Statera modela esa vía pero todavía no la recorre.'
        );
    }

    /** @param  list<string>  $motivos */
    public static function bloqueada(array $motivos): self
    {
        return new self('Todavía no se puede iniciar la declaración: '.implode(' ', $motivos));
    }

    public static function yaEnPreparacion(): self
    {
        return new self('Este sistema ya tiene una declaración en preparación. Termínala o retírala antes de empezar otra.');
    }

    public static function versionNoValida(string $motivo): self
    {
        return new self("Esa versión no puede respaldar la declaración: {$motivo}");
    }

    public static function sinMotivo(): self
    {
        return new self('Retirar una declaración exige decir por qué: es la pregunta que el auditor hará.');
    }

    public static function urlNoValida(): self
    {
        return new self('La dirección del distintivo tiene que ser una URL pública, que empiece por http:// o https://.');
    }

    public static function fechaNoValida(): self
    {
        return new self('El distintivo no puede haberse publicado antes de la declaración ni en una fecha futura.');
    }
}
