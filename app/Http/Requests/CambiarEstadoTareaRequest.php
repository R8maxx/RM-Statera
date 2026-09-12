<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\EstadoTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de una tarea, desde su ficha.
 *
 * La nota es obligatoria al descartar y opcional en el resto: descartar una
 * tarea es decidir que no se hará, y esa decisión el auditor la puede
 * cuestionar. El dominio lo vuelve a comprobar —`CambiarEstadoTarea`—, porque la
 * regla vale también para un importador o para el seeder; esto es para que el
 * mensaje llegue al campo.
 */
class CambiarEstadoTareaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoTarea::class)],
            'nota' => [
                Rule::requiredIf(fn (): bool => $this->input('estado') === EstadoTarea::Descartada->value),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'Di por qué se descarta: es una decisión, y sin motivo el hallazgo se queda sin rastro de qué se hizo con él.',
        ];
    }
}
