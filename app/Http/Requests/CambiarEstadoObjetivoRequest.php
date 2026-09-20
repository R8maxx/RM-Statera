<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de un objetivo, desde su ficha.
 *
 * La nota es obligatoria en tres transiciones y opcional en el resto. El dominio
 * lo vuelve a comprobar —`CambiarEstadoObjetivo`—, porque la regla vale también
 * para un importador; esto es para que el mensaje llegue al campo en vez de subir
 * como una excepción.
 *
 * - **retirar**, que es decidir que el objetivo ya no se persigue;
 * - **darlo por no alcanzado**, donde «por qué» es lo que la revisión por la
 *   dirección va a preguntar del año que termina;
 * - **reabrirlo** desde algo ya cerrado, que es darle otro plazo y donde sin nota
 *   el histórico enseñaría un ir y venir sin explicar ninguno.
 *
 * Aprobar **no** exige nota: la firma ya es el gesto, y lo que la 6.2 pide por
 * escrito en ese momento es el plazo, que tiene su propia columna.
 */
class CambiarEstadoObjetivoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoObjetivo::class)],
            'nota' => [
                Rule::requiredIf(fn (): bool => $this->exigeMotivo()),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'Esta transición exige decir por qué: sin motivo escrito, no se puede defender delante de un auditor.',
        ];
    }

    private function exigeMotivo(): bool
    {
        $destino = EstadoObjetivo::tryFrom((string) $this->input('estado'));
        $actual = $this->route('objetivo');

        if ($destino === EstadoObjetivo::Retirado || $destino === EstadoObjetivo::NoAlcanzado) {
            return true;
        }

        return $destino === EstadoObjetivo::Aprobado
            && $actual instanceof Objetivo
            && $actual->estado->esCerrado();
    }
}
