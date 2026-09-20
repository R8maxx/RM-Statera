<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Models\Persona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una persona. § 4.8.
 *
 * **`user_id` es único en toda la tabla**, no sólo dentro de la organización: una
 * cuenta pertenece como mucho a una persona, y dos organizaciones no comparten
 * cuentas. La regla `unique` se escribe sin acotar a propósito.
 *
 * **La fecha de baja se admite aquí y no tiene ruta propia**, a diferencia de las
 * transiciones de otros módulos: dar de baja a alguien no es una máquina de
 * estados con fechas acopladas, es escribir el día que se fue. Lo que sí hace la
 * baja es encender el aviso de la checklist de salida, y eso se deriva.
 */
class GuardarPersonaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $persona = $this->route('persona');
        $id = $persona instanceof Persona ? $persona->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('personas', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'nombre' => ['required', 'string', 'max:255'],
            'puesto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],

            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
                Rule::unique('personas', 'user_id')->ignore($id),
            ],

            'fecha_alta' => ['required', 'date'],
            'fecha_baja' => ['nullable', 'date', 'after_or_equal:fecha_alta'],

            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_baja.after_or_equal' => 'Nadie se va antes de entrar: revisa las dos fechas.',
            'user_id.unique' => 'Esa cuenta ya está vinculada a otra persona.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'cuenta de Statera',
            'fecha_alta' => 'fecha de alta',
            'fecha_baja' => 'fecha de baja',
        ];
    }
}
