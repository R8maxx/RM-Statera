<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ata la versión firmada de la Declaración de Conformidad a la declaración.
 *
 * Sólo se valida la forma. Que la versión sea del tipo correcto, del mismo
 * sistema, esté emitida y se firmara después de iniciar la declaración lo
 * comprueba `RegistrarDeclaracion`, porque vale también fuera de este formulario.
 */
class RegistrarDeclaracionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documento_version_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento_version_id.required' => 'Elige la versión firmada de la Declaración de Conformidad.',
        ];
    }
}
