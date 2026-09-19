<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Models\Indicador;
use RuntimeException;

/**
 * Se ha pedido calcular un indicador que no declara cálculo.
 *
 * No debería ocurrir —el `CHECK` de la tabla acopla `origen` y `calculo` en las
 * dos direcciones—, y por eso es una excepción y no una rama silenciosa: si
 * ocurre, la restricción se ha roto o alguien ha llamado al camino equivocado, y
 * las dos cosas hay que verlas.
 */
final class IndicadorNoCalculable extends RuntimeException
{
    public function __construct(Indicador $indicador)
    {
        parent::__construct(sprintf(
            'El indicador %s es de registro manual: no tiene cálculo que ejecutar.',
            $indicador->codigo,
        ));
    }
}
