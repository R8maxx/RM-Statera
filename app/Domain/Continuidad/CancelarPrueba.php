<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una prueba de continuidad planificada.
 *
 * **Sólo desde `planificada`**, por el mismo motivo que
 * `RegistrarResultadoPrueba`: no hay un `CambiarEstadoPrueba` genérico.
 *
 * **El motivo se guarda dos veces**: en `motivo_cancelacion` de la propia
 * prueba —lo que impone `pruebas_continuidad_cancelada_check`— y como `nota`
 * de la transición, para que el histórico cuente la misma historia sin tener
 * que ir a buscarla a otra columna.
 */
final class CancelarPrueba
{
    public function __construct(private readonly RegistroTransicionesPrueba $registro) {}

    /**
     * @throws TransicionDePruebaNoPermitida
     */
    public function __invoke(PruebaContinuidad $prueba, string $motivo, ?User $usuario = null): PruebaContinuidad
    {
        $actual = $prueba->estado;

        if ($actual !== EstadoPrueba::Planificada) {
            throw TransicionDePruebaNoPermitida::entre($actual, EstadoPrueba::Cancelada);
        }

        if (trim($motivo) === '') {
            throw TransicionDePruebaNoPermitida::sinMotivo(EstadoPrueba::Cancelada);
        }

        return DB::transaction(function () use ($prueba, $motivo, $actual, $usuario): PruebaContinuidad {
            $prueba->update([
                'estado' => EstadoPrueba::Cancelada->value,
                'motivo_cancelacion' => $motivo,
            ]);

            $this->registro->registrar($prueba, $actual, EstadoPrueba::Cancelada, $usuario, $motivo);

            return $prueba->refresh();
        });
    }
}
