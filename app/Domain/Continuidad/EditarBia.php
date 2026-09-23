<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
 *
 * **Sólo acepta los campos de contenido, no los de ciclo de vida.**
 * `BiaServicio::$fillable` incluye `estado`, `aprobado_por_id`,
 * `fecha_aprobacion` y `fecha_revision` porque `CambiarEstadoBia` los escribe
 * con `update()`; pasarlos aquí tal cual los dejaría escribir a un caller que
 * se salte `CambiarEstadoBia` — el estado cambiaría sin fila en
 * `bia_servicio_transiciones` (invariante 7), sin motivo exigido y, para
 * `obsoleto`, sin que ningún `CHECK` lo detecte. Se usa `InvalidArgumentException`
 * y no una excepción del dominio propia del BIA: un campo fuera de la lista no
 * es una decisión de negocio que la interfaz tenga que explicar —es un error
 * de integración de quien llama—, el mismo criterio que ya usa
 * `ValoracionDimensiones` para una dimensión que no reconoce.
 */
final class EditarBia
{
    private const NOTA_EDICION = 'Editado tras la aprobación: vuelve a borrador.';

    /**
     * Los únicos campos que una edición puede tocar. Todo lo demás pertenece
     * al ciclo de vida y sólo lo escribe `CambiarEstadoBia`.
     *
     * @var list<string>
     */
    private const CAMPOS_EDITABLES = [
        'impacto_4h',
        'impacto_1d',
        'impacto_3d',
        'impacto_1s',
        'impacto_1m',
        'rto_horas',
        'rpo_horas',
        'justificacion',
        'responsable_id',
    ];

    public function __construct(private readonly CambiarEstadoBia $cambiarEstado) {}

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(BiaServicio $bia, array $atributos, ?User $usuario = null): BiaServicio
    {
        $this->exigirCamposEditables($atributos);

        return DB::transaction(function () use ($bia, $atributos, $usuario): BiaServicio {
            $eraAprobado = $bia->estado === EstadoBia::Aprobado;

            $bia->update($atributos);

            if ($eraAprobado) {
                return ($this->cambiarEstado)($bia->refresh(), EstadoBia::Borrador, $usuario, self::NOTA_EDICION);
            }

            return $bia->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws InvalidArgumentException
     */
    private function exigirCamposEditables(array $atributos): void
    {
        $noPermitidos = array_diff(array_keys($atributos), self::CAMPOS_EDITABLES);

        if ($noPermitidos !== []) {
            throw new InvalidArgumentException(sprintf(
                'EditarBia no admite el campo «%s»: es un campo de ciclo de vida y sólo lo escribe '
                .'CambiarEstadoBia, con su histórico y su motivo.',
                implode('», «', $noPermitidos),
            ));
        }
    }
}
