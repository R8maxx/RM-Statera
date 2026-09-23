<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La no conformidad que se abre desde la ficha de una prueba de continuidad.
 *
 * Subconjunto de `GuardarNoConformidadRequest`, calcando `AbrirAccionCorrectiva
 * Request`: **sin `origen`, sin `hallazgo_id` y sin `incidente_id`**, porque los
 * pone `DerivarDePrueba` y no se preguntan —preguntarlos invita a cambiarlos, y
 * una no conformidad que dice venir de una prueba y en realidad no lo hace
 * pierde justo lo que la hacía trazable—.
 */
class DerivarNoConformidadDePruebaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('no_conformidades', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],
            'descripcion' => ['required', 'string', 'max:5000'],
            'correccion_inmediata' => ['nullable', 'string', 'max:5000'],
            'analisis_causa_raiz' => ['nullable', 'string', 'max:5000'],
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
            'descripcion.required' => 'Una no conformidad sin descripción es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'responsable_id' => 'responsable',
            'analisis_causa_raiz' => 'análisis de causa raíz',
            'correccion_inmediata' => 'corrección inmediata',
            'fecha_deteccion' => 'fecha de detección',
            'fecha_prevista' => 'fecha prevista',
        ];
    }
}
