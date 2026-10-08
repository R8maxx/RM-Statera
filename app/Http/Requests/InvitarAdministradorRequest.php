<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Enums\PerfilPlataforma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invitar a alguien a administrar la plataforma (punto 49).
 *
 * **Con la contraseña de quien invita en la misma petición**, como
 * `CerrarSesionesRequest`: quien encuentre una sesión abierta no puede darse
 * un administrador. Y no con `password.confirm`, que vuelve con un `GET`.
 *
 * El correo es único en todo Statera. Convertir en administradora una cuenta
 * de cliente que ya existe sigue siendo cosa de la consola.
 */
class InvitarAdministradorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'perfil' => ['required', Rule::enum(PerfilPlataforma::class)],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya hay una cuenta con ese correo en Statera.',
            'password.current_password' => 'La contraseña no es la de tu cuenta.',
        ];
    }

    public function perfil(): PerfilPlataforma
    {
        return PerfilPlataforma::from((string) $this->validated('perfil'));
    }
}
