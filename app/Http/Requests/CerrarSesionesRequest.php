<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cerrar sesiones de la propia cuenta en otros navegadores.
 *
 * **Pide la contraseña en la misma petición**, y no con `password.confirm`: ese
 * middleware vuelve al destino con un `GET` después de confirmar, y esto es un
 * `DELETE`. Sin contraseña, quien encuentre una sesión olvidada abierta echa al
 * dueño de todas las demás y se queda solo dentro.
 */
class CerrarSesionesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => 'La contraseña no es la de esta cuenta.',
        ];
    }
}
