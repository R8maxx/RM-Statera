<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La acción masiva que pone la fecha de revisión a los activos seleccionados.
 *
 * Los identificadores se comprueban contra la organización activa. No es
 * redundante con el scope global: la regla `exists` se escribe explícita para
 * que un id ajeno falle en la validación con un mensaje, y no más adelante con
 * un 404 a mitad del recorrido.
 */
class MarcarRevisadosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activos' => ['required', 'array', 'min:1'],
            'activos.*' => [
                'integer',
                Rule::exists('activos', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
        ];
    }
}
