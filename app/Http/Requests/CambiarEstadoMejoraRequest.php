<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Mejora\Enums\EstadoMejora;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de una oportunidad de mejora, desde su ficha.
 *
 * **Una sola transición exige nota: descartar.** El dominio lo vuelve a comprobar
 * —`CambiarEstadoMejora`—, porque la regla vale también para un importador; esto
 * es para que el mensaje llegue al campo en vez de subir como una excepción.
 *
 * Implantar no la exige: lo que se hizo lo cuentan sus tareas, y pedir un texto
 * para cerrar lo que sí se hizo convierte en trámite el único gesto del registro
 * que da alegrías.
 */
class CambiarEstadoMejoraRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoMejora::class)],
            'nota' => [
                Rule::requiredIf(fn (): bool => EstadoMejora::tryFrom((string) $this->input('estado')) === EstadoMejora::Descartada),
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
            'nota.required' => 'Descartar una mejora exige decir por qué: sin motivo escrito, el registro se llena de ideas muertas sin explicación.',
        ];
    }
}
