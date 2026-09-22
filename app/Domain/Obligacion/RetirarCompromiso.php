<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;

/**
 * Retira un compromiso que ha dejado de aplicar.
 *
 * **Retirar no es borrar**, y la distinción es la misma que separa `retirado` de
 * `dado_de_baja` en un activo: un compromiso retirado conserva la prueba de que se
 * cumplió mientras aplicaba, que es justo lo que un auditor pide del periodo
 * anterior. Borrarlo se lleva el histórico por delante.
 *
 * Deja de contar en el calendario y en el panel, porque los tres scopes que los
 * alimentan arrancan por `activos()`.
 */
final class RetirarCompromiso
{
    public function __invoke(Compromiso $compromiso, ?string $motivo = null): Compromiso
    {
        $compromiso->update([
            'activo' => false,
            'notas' => $motivo ?? $compromiso->notas,
        ]);

        return $compromiso->refresh();
    }
}
