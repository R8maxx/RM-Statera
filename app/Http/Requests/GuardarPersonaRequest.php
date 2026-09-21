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

            /*
             * `nombre` NO se valida aquí, y tampoco se manda: lo calcula
             * PostgreSQL desde estas tres. Si volviera a aparecer en esta lista,
             * `Persona::create($request->validated())` lo **descartaría en
             * silencio** —no está en `$fillable`— y el formulario parecería
             * funcionar mientras el dato se pierde.
             */
            'nombre_pila' => ['required', 'string', 'max:255'],
            'apellido1' => ['nullable', 'string', 'max:255'],
            'apellido2' => ['nullable', 'string', 'max:255'],

            /*
             * Sin validar la letra ni el formato: un NIE, un pasaporte y un
             * documento extranjero no la tienen, y rechazarlos sería impedir dar
             * de alta a alguien que trabaja aquí. Lo único que se impone es que no
             * haya dos iguales en la misma organización.
             */
            'nif' => [
                'nullable', 'string', 'max:32',
                Rule::unique('personas', 'nif')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'telefono' => ['nullable', 'string', 'max:32'],
            'telefono_fijo' => ['nullable', 'string', 'max:32'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],

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
     * El NIF se normaliza antes de validarlo.
     *
     * Sin esto, «12345678z», «12345678Z» y «  12345678-Z » son tres documentos
     * distintos para el índice único, y la unicidad que promete el campo no
     * existe. Se toca la grafía y no el contenido: ni se valida la letra ni se
     * rechaza lo que no parezca español.
     */
    protected function prepareForValidation(): void
    {
        $nif = $this->input('nif');

        if (is_string($nif)) {
            $this->merge(['nif' => mb_strtoupper(preg_replace('/[\s-]+/u', '', $nif) ?? $nif)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_baja.after_or_equal' => 'Nadie se va antes de entrar: revisa las dos fechas.',
            'user_id.unique' => 'Esa cuenta ya está vinculada a otra persona.',
            'nif.unique' => 'Ya hay otra persona con ese documento en la organización.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento tiene que ser anterior a hoy.',
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
            'nombre_pila' => 'nombre',
            'apellido1' => 'primer apellido',
            'apellido2' => 'segundo apellido',
            'nif' => 'NIF o documento',
            'telefono_fijo' => 'teléfono fijo',
            'fecha_nacimiento' => 'fecha de nacimiento',
        ];
    }
}
