<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\ParteInteresada;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una parte interesada contra el borrador que haya abierto.
 *
 * Mismo reparto que `RegistrarCuestion`, y por lo mismo: el análisis de alta se
 * pone y no se pregunta, y el borrador se estrena solo si no había ninguno.
 *
 * **Sin sus requisitos**, que llegan después por `GuardarRequisitosInteresado`. Es
 * el mismo criterio que separa dar de alta una tarea de rellenar su lista de
 * comprobación: identificar a quién le importa la seguridad de la organización y
 * escribir qué le exige cada uno son dos trabajos, y el segundo se hace con la
 * ficha delante.
 */
final class RegistrarParteInteresada
{
    public function __construct(private readonly AnalisisEnCurso $analisis) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null): ParteInteresada
    {
        return DB::transaction(function () use ($atributos, $usuario): ParteInteresada {
            $borrador = $this->analisis->borradorObligatorio($usuario);

            $parte = ParteInteresada::query()->create([
                ...$atributos,
                'analisis_alta_id' => $borrador->id,
                'analisis_baja_id' => null,
                'motivo_baja' => null,
            ]);

            return $parte->refresh();
        });
    }
}
