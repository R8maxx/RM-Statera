<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Evidencia\Models\Evidencia;

/**
 * Adjunta la evidencia a una prueba de continuidad ya realizada.
 *
 * **Es la única escritura sobre una prueba terminal.** `EditarPrueba` sólo
 * acepta una prueba planificada —lo realizado no se reescribe—, pero la
 * evidencia llega a menudo después: el informe del restore o el acta del
 * simulacro se redactan días más tarde. Sin esta puerta, la tira de «Lo que
 * falta» pedía un dato que nadie podía poner.
 *
 * **Sólo toca `evidencia_id`**: ni el resultado, ni la fecha, ni lo alcanzado
 * por servicio. **Reemplaza sin preguntar** si ya había una, porque adjuntar la
 * equivocada tiene que poder corregirse, y el valor anterior no se pierde: lo
 * guarda la traza (`RegistraTraza`). **No es un cambio de estado**, así que no
 * escribe fila en el histórico de transiciones: la prueba sigue `realizada`.
 */
final class AdjuntarEvidenciaPrueba
{
    /**
     * @throws TransicionDePruebaNoPermitida
     */
    public function __invoke(PruebaContinuidad $prueba, Evidencia $evidencia): PruebaContinuidad
    {
        if ($prueba->estado !== EstadoPrueba::Realizada) {
            throw TransicionDePruebaNoPermitida::sinEvidenciaQueAdjuntar($prueba->estado);
        }

        $prueba->update(['evidencia_id' => $evidencia->id]);

        return $prueba->refresh();
    }
}
