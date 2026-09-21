<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\Models\Puesto;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un puesto.
 *
 * `reporta_a_id` se valida aquí —que exista y que no sea él mismo— pero **el ciclo
 * no**: es una condición entre filas y vive en `AsignarSuperior`, porque vale
 * igual para un importador y para el seeder.
 */
class GuardarPuestoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $puesto = $this->route('puesto');
        $id = $puesto instanceof Puesto ? $puesto->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('puestos', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'titulo' => ['required', 'string', 'max:255'],

            'reporta_a_id' => [
                'nullable',
                'integer',
                Rule::exists('puestos', 'id'),
                // El auto-bucle: lo tapa además un `CHECK`, pero el error de una
                // restricción no explica nada a quien acaba de elegirlo.
                Rule::notIn($id === null ? [] : [$id]),
            ],

            'mision' => ['nullable', 'string', 'max:2000'],
            'funciones' => ['nullable', 'string', 'max:5000'],
            'competencias' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reporta_a_id.not_in' => 'Un puesto no puede depender de sí mismo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reporta_a_id' => 'puesto del que depende',
        ];
    }

    /**
     * El desplegable de superior es opcional, así que manda el centinela de
     * «ninguno» cuando se deja sin elegir: Reka prohíbe el valor vacío en un
     * `SelectItem`. Sin esta lista, «depende de nadie» llegaría como la cadena
     * `__ninguno__` y el `integer` la rechazaría.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['reporta_a_id'];
    }
}
