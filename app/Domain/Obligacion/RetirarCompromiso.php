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
 *
 * **El motivo va a `motivo_retirada` y no a `notas`.** Escribía en `notas`, que es
 * un campo del formulario, editable y buscable: retirar con motivo borraba lo que
 * hubiera escrito quien mantiene el compromiso. Son dos textos con dos dueños y dos
 * momentos, y ahora tienen dos columnas.
 */
final class RetirarCompromiso
{
    public function __invoke(Compromiso $compromiso, ?string $motivo = null): Compromiso
    {
        $compromiso->update([
            'activo' => false,
            'motivo_retirada' => $motivo,
        ]);

        return $compromiso->refresh();
    }
}
