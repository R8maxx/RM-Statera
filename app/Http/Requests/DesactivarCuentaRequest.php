<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Desactivar una cuenta. El motivo es opcional pero se ofrece, porque «¿por
 * qué ya no entra?» es lo primero que se pregunta seis meses después.
 */
class DesactivarCuentaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
