<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dar de baja una organización (punto 46). El motivo es obligatorio: es lo
 * primero que se pregunta cuando alguien quiere volver.
 */
class BajaOrganizacionRequest extends FormRequest
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
}
