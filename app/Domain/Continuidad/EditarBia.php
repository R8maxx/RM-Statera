<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Edita un BIA, y lo devuelve a borrador si estaba aprobado.
 *
 * **Un BIA aprobado que se edita deja de estar vigente**, y decirlo con un
 * estado y no con un simple guardado es lo que impide que la organización siga
 * confiando en un RTO que alguien acaba de cambiar. El paso a borrador pasa
 * por `CambiarEstadoBia` y no por un `update` directo, para que quede en el
 * histórico igual que cualquier otra transición — la nota la pone el sistema
 * porque la causa es la propia edición, no una decisión que alguien tenga que
 * explicar aparte.
 */
final class EditarBia
{
    private const NOTA_EDICION = 'Editado tras la aprobación: vuelve a borrador.';

    public function __construct(private readonly CambiarEstadoBia $cambiarEstado) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(BiaServicio $bia, array $atributos, ?User $usuario = null): BiaServicio
    {
        return DB::transaction(function () use ($bia, $atributos, $usuario): BiaServicio {
            $eraAprobado = $bia->estado === EstadoBia::Aprobado;

            $bia->update($atributos);

            if ($eraAprobado) {
                return ($this->cambiarEstado)($bia->refresh(), EstadoBia::Borrador, $usuario, self::NOTA_EDICION);
            }

            return $bia->refresh();
        });
    }
}
