<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Retirar una declaración exige motivo.
 *
 * El dominio lo vuelve a exigir (`RetirarConformidad`), porque la regla vale
 * también para un importador; esto es para que el mensaje llegue al campo.
 */
class RetirarConformidadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Retirar una declaración exige decir por qué: es la pregunta que el auditor hará.',
        ];
    }
}
