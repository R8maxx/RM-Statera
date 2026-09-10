<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Declarar que un activo depende de otro.
 *
 * Aquí sólo se comprueba que el otro activo existe y es de la misma
 * organización. Que el vínculo no cierre un ciclo lo decide
 * `RegistrarDependencia`, porque es una regla del dominio y tiene que valer
 * también para un importador o para el seeder, que nunca pasan por un
 * formulario.
 */
class VincularDependenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'depende_de_id' => [
                'required',
                'integer',
                Rule::exists('activos', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['depende_de_id' => 'activo del que depende'];
    }
}
