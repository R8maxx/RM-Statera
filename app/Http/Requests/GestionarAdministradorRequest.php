<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Enums\PerfilPlataforma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambiar el perfil de un administrador o retirarle (punto 49), siempre con la
 * contraseña de quien lo hace en la misma petición.
 */
class GestionarAdministradorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'perfil' => [$this->routeIs('plataforma.administradores.perfil') ? 'required' : 'prohibited', Rule::enum(PerfilPlataforma::class)],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.current_password' => 'La contraseña no es la de tu cuenta.',
        ];
    }

    public function perfil(): PerfilPlataforma
    {
        return PerfilPlataforma::from((string) $this->validated('perfil'));
    }
}
