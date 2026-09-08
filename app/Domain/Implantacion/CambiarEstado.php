<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Excepciones\TransicionNoPermitida;
use App\Domain\Implantacion\Models\Implantacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado por decisión de una persona.
 *
 * Valida contra la máquina de estados, aplica el cambio y registra el histórico
 * en la misma transacción: un estado nuevo sin su fila de transición sería un
 * agujero en la traza.
 */
final class CambiarEstado
{
    public function __construct(private readonly RegistroTransiciones $registro) {}

    public function __invoke(
        Implantacion $implantacion,
        EstadoImplantacion $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): Implantacion {
        $actual = $implantacion->estado;

        if ($actual === $nuevo) {
            return $implantacion;
        }

        // `no_aplica` se deriva de la valoración del sistema, no se elige.
        if ($nuevo === EstadoImplantacion::NoAplica) {
            throw TransicionNoPermitida::noAplicaEsDerivado($actual);
        }

        if ($actual === EstadoImplantacion::NoAplica) {
            throw new TransicionNoPermitida(
                $actual,
                $nuevo,
                'La medida no se le exige a este sistema; vuelve a exigirse recalculando tras cambiar la valoración.',
            );
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionNoPermitida($actual, $nuevo);
        }

        return DB::transaction(function () use ($implantacion, $actual, $nuevo, $usuario, $nota): Implantacion {
            $implantacion->update(['estado' => $nuevo->value]);

            $this->registro->registrar($implantacion, $actual, $nuevo, $usuario, $nota);

            return $implantacion->refresh();
        });
    }
}
