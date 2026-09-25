<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Models\Proveedor;

/**
 * El siguiente código libre, `PRV-001`. Una propuesta: si la organización ya
 * tiene su nomenclatura de proveedores, es la que vale.
 */
final class CodigoProveedor
{
    public function siguiente(): string
    {
        $ultimo = Proveedor::query()
            ->where('codigo', 'like', 'PRV-%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from 5), '[^0-9]', '', 'g'), '')::int) as ultimo")
            ->value('ultimo');

        return sprintf('PRV-%03d', ((int) $ultimo) + 1);
    }
}
