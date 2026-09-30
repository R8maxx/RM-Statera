<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular una evidencia con varios requisitos a la vez, desde su ficha.
 *
 * Es el mismo vínculo que `VincularEvidenciaRequest` visto desde el otro lado:
 * quien acaba de subir la captura del IdP sabe que prueba `A.8.5` y `op.acc.5`,
 * y buscarlos uno a uno en sus fichas era dar dos vueltas para un solo gesto.
 *
 * `exists` pasa por Row Level Security, igual que allí: una implantación de otra
 * organización no está, y falla como «no existe».
 */
class VincularRequisitosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'implantaciones' => ['required', 'array', 'min:1', 'max:100'],
            'implantaciones.*' => ['integer', 'distinct', Rule::exists('implantaciones', 'id')],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'implantaciones.required' => 'Elige al menos un requisito.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'implantaciones' => 'requisitos',
            'implantaciones.*' => 'requisito',
        ];
    }
}
