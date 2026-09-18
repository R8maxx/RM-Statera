<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\CuestionContexto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una cuestión del DAFO, contra el borrador que haya abierto.
 *
 * **El análisis de alta se pone, no se pregunta**, igual que el origen de una
 * acción correctiva: preguntarlo invita a cambiarlo, y cambiarlo sería decir que
 * una debilidad que se acaba de escribir ya estaba sobre la mesa el año pasado.
 * Si no hay borrador abierto se estrena aquí, que es lo que hace que el primer
 * gesto de trabajo no exija saber que el concepto existe.
 *
 * El código llega desde fuera —lo propone `CodigoContexto` y el formulario deja
 * cambiarlo— porque una organización que ya tenía su DAFO en una hoja de cálculo
 * llega con códigos propios.
 */
final class RegistrarCuestion
{
    public function __construct(private readonly AnalisisEnCurso $analisis) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null): CuestionContexto
    {
        return DB::transaction(function () use ($atributos, $usuario): CuestionContexto {
            $borrador = $this->analisis->borradorObligatorio($usuario);

            $cuestion = CuestionContexto::query()->create([
                ...$atributos,
                'analisis_alta_id' => $borrador->id,
                'analisis_baja_id' => null,
                'motivo_baja' => null,
            ]);

            // `es_climatica` lo pone la base con su valor por defecto, como el
            // estado de una tarea: sin esto, lo primero que lo lea recibe un nulo.
            return $cuestion->refresh();
        });
    }
}
