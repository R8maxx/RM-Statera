<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Models\Indicador;

/**
 * Propone el código del siguiente indicador: `IND-01`.
 *
 * **Propone y no impone**, igual que `CodigoNoConformidad`: una organización que
 * ya llevaba su cuadro de mando en una hoja llega con códigos propios —«SEG-03»,
 * «KPI-12»— y obligarla a renumerar rompe las referencias de sus actas.
 *
 * **Sin año en el prefijo, a diferencia de una no conformidad o de un análisis
 * del contexto.** Un indicador no pertenece a un ejercicio: se declara una vez y
 * se sigue midiendo durante años, y su gracia es precisamente comparar 2026 con
 * 2029. Meterle el año al código obligaría a renumerarlo cada enero y rompería
 * la serie que justifica el módulo.
 *
 * Se cuenta sobre el máximo y no sobre el total: con dos retirados, el total
 * daría un código ya usado.
 */
final class CodigoIndicador
{
    public function siguiente(): string
    {
        $prefijo = 'IND-';

        $ultimo = Indicador::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
