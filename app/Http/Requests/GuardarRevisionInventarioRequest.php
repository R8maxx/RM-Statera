<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de una revisión del inventario.
 *
 * `alcance` es obligatorio, y no por rigor administrativo: una revisión parcial
 * es perfectamente legítima —«el parque de puestos», «la cuenta de AWS»— pero
 * una que no dice qué miró no demuestra nada. Es la diferencia entre poder
 * responder «el inventario se revisa» y poder responder «esto se revisó el 12 de
 * marzo y esto otro no se ha mirado desde el alta».
 */
class GuardarRevisionInventarioRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Una revisión con fecha futura no es una revisión, es un plan.
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'alcance' => ['required', 'string', 'max:255'],
            'altas' => ['required', 'integer', 'min:0'],
            'bajas' => ['required', 'integer', 'min:0'],
            'desviaciones' => ['nullable', 'string', 'max:10000'],
            'acciones' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'responsable_id' => 'responsable de la revisión',
            'alcance' => 'alcance revisado',
        ];
    }

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }
}
