<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Excepciones\TransicionDeTareaNoPermitida;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de una tarea, con su histórico.
 *
 * Valida contra la máquina de estados, aplica el cambio y registra la transición
 * en la misma transacción: un estado nuevo sin su fila sería un agujero en la
 * traza.
 *
 * **Cerrar pone la fecha de cierre y reabrir la quita**, aquí y no en el
 * formulario. La base lo exige con un `CHECK` que acopla las dos columnas, y
 * dejar que la ponga quien llame significa que el día que se cierre una tarea
 * desde un importador o desde un job, la inserción falle con un error de
 * restricción que no menciona la palabra «cierre».
 */
final class CambiarEstadoTarea
{
    public function __construct(private readonly RegistroTransicionesTarea $registro) {}

    public function __invoke(
        Tarea $tarea,
        EstadoTarea $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): Tarea {
        $actual = $tarea->estado;

        if ($actual === $nuevo) {
            return $tarea;
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionDeTareaNoPermitida($actual, $nuevo);
        }

        // Descartar sin decir por qué deja el hallazgo sin rastro de qué se
        // decidió. Es la única transición que exige nota.
        if ($nuevo === EstadoTarea::Descartada && ($nota === null || trim($nota) === '')) {
            throw new TransicionDeTareaNoPermitida(
                $actual,
                $nuevo,
                'Descartar una tarea exige decir por qué: es una decisión que el auditor puede cuestionar.',
            );
        }

        return DB::transaction(function () use ($tarea, $actual, $nuevo, $usuario, $nota): Tarea {
            $tarea->update([
                'estado' => $nuevo->value,
                'fecha_cierre' => $nuevo->esCerrada() ? Carbon::today() : null,
            ]);

            $this->registro->registrar($tarea, $actual, $nuevo, $usuario, $nota);

            return $tarea->refresh();
        });
    }
}
