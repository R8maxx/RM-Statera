<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Retirar una línea del plan de comunicación exige decir por qué.
 *
 * No se borra: lo que se comunicó para cumplirla sigue siendo un hecho, y el
 * auditor puede preguntar por qué dejó de hacerse.
 */
class RetirarComunicacionPrevistaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo_retirada' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_retirada.required' => 'Retirar una comunicación del plan exige decir por qué: el auditor puede preguntarlo.',
        ];
    }
}
