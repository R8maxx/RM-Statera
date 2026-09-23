<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La oportunidad de mejora que se abre desde la ficha de una prueba de
 * continuidad.
 *
 * Subconjunto de `GuardarMejoraRequest`: **sin `origen` y sin `hallazgo_id`**,
 * porque los pone `DerivarDePrueba` y no se preguntan, mismo criterio que
 * `DerivarNoConformidadDePruebaRequest`.
 */
class DerivarMejoraDePruebaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('mejoras', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'beneficio_esperado' => ['nullable', 'string', 'max:5000'],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'fecha_deteccion' => ['required', 'date'],
            'fecha_prevista' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Una mejora sin enunciado es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'mejora',
            'responsable_id' => 'responsable',
            'beneficio_esperado' => 'beneficio esperado',
            'fecha_deteccion' => 'fecha de detección',
            'fecha_prevista' => 'fecha prevista',
        ];
    }
}
