<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\GuardarPasos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La checklist de incorporación o de salida, guardada entera.
 *
 * Calcado del `FormRequest` de las subtareas: llega la lista completa, el orden va
 * implícito en la posición del array y el tope existe para que nadie convierta una
 * checklist en un documento.
 */
class GuardarPasosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoPasoPersona::class)],
            'pasos' => ['present', 'array', 'max:'.GuardarPasos::TOPE],
            'pasos.*.id' => ['nullable', 'integer'],
            'pasos.*.titulo' => ['nullable', 'string', 'max:255'],
            'pasos.*.hecho' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pasos.max' => sprintf(
                'Una checklist de %d pasos ya no es una checklist: eso es un procedimiento, y va en un documento.',
                GuardarPasos::TOPE,
            ),
        ];
    }
}
