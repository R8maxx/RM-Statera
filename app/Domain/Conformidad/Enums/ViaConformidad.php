<?php

declare(strict_types=1);

namespace App\Domain\Conformidad\Enums;

use App\Domain\Catalogo\Enums\CategoriaEns;

/**
 * Por dónde se llega a la conformidad con el ENS. § 4.17.
 *
 * **Básica se declara, media y alta se certifican.** Para categoría básica, el RD
 * 311/2022 no exige auditoría por entidad acreditada: basta con que la
 * organización se autoevalúe y lo declare. Media y alta necesitan una auditoría
 * de una entidad acreditada por ENAC, que es la que emite la Certificación de
 * Conformidad.
 *
 * **La certificación se modela y no se implementa**, que es lo que pide la
 * especificación: el caso existe, la tabla tiene sus columnas y su `CHECK`, y
 * `IniciarDeclaracion` la rechaza con un mensaje que lo dice.
 */
enum ViaConformidad: string
{
    case Declaracion = 'declaracion';
    case Certificacion = 'certificacion';

    /** La vía que le toca a una categoría. La regla vive aquí y en el `CHECK`. */
    public static function paraCategoria(CategoriaEns $categoria): self
    {
        return $categoria === CategoriaEns::Basica ? self::Declaracion : self::Certificacion;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Declaracion => 'Declaración de Conformidad',
            self::Certificacion => 'Certificación de Conformidad',
        };
    }

    /** Si Statera recorre esta vía de principio a fin. */
    public function implementada(): bool
    {
        return $this === self::Declaracion;
    }
}
