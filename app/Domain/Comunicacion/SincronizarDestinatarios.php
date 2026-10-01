<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion;

use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Contexto\Models\ParteInteresada;

/**
 * Fija a qué partes interesadas va una línea del plan.
 *
 * **Sólo las vigentes y sólo las de esta organización**: los ids pasan por la
 * consulta con scope antes de tocar la pivote, porque `sync()` no filtra nada y
 * un id ajeno acabaría colgado de la previsión. Y `sync()` no rellena columnas
 * extra, así que `organizacion_id` se pasa a mano — el patrón de
 * `VulnerabilidadController::sincronizarActivos()`.
 */
final class SincronizarDestinatarios
{
    /**
     * @param  list<int>  $ids
     */
    public function __invoke(ComunicacionPrevista $prevista, array $ids): void
    {
        $validos = ParteInteresada::query()
            ->vigentes()
            ->whereKey($ids)
            ->pluck('partes_interesadas.id')
            ->all();

        $prevista->partesInteresadas()->sync(
            array_fill_keys($validos, ['organizacion_id' => $prevista->organizacion_id]),
        );
    }
}
