<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Models\Indicador;

/**
 * Da de alta o actualiza un indicador.
 *
 * Concentra la única regla que el `CHECK` impone y el formulario no puede dar
 * por supuesta: **un indicador es calculado o es manual, y lo que sobra se
 * limpia**. Sin esto, cambiar uno calculado a manual dejaría el `calculo`
 * puesto y la fila la rechazaría PostgreSQL con un error de restricción que no
 * menciona ninguna de las dos palabras.
 *
 * Vive en el dominio y no en el `FormRequest` porque vale igual para el seeder y
 * para un importador, que es el mismo motivo por el que la fecha de cierre de
 * una tarea la pone `CambiarEstadoTarea`.
 */
final readonly class RegistrarIndicador
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __invoke(array $datos, ?Indicador $indicador = null): Indicador
    {
        $origen = OrigenMedicion::from((string) ($datos['origen'] ?? OrigenMedicion::Manual->value));

        if ($origen === OrigenMedicion::Calculado) {
            $datos['formula_o_fuente'] = null;
        } else {
            $datos['calculo'] = null;
            // Un marco acota un cálculo; sin cálculo no acota nada, y dejarlo
            // puesto haría creer que la cifra manual sale sólo de ese marco.
            $datos['marco_id'] = null;
        }

        if ($indicador instanceof Indicador) {
            $indicador->fill($datos)->save();

            return $indicador->refresh();
        }

        return Indicador::query()->create($datos)->refresh();
    }
}
