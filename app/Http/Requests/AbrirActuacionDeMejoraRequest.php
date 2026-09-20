<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La actuación que se abre desde la ficha de una mejora.
 *
 * Es un subconjunto de `GuardarTareaRequest` **y le falta `origen` a propósito**:
 * lo pone `AbrirActuacionDeMejora`, y es `OrigenTarea::Mejora` y no
 * `NoConformidad` — la 10.1 y la 10.2 son dos cláusulas distintas.
 */
class AbrirActuacionDeMejoraRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'prioridad' => ['required', Rule::enum(PrioridadTarea::class)],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'fecha_limite' => ['nullable', 'date'],
            'coste_estimado' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Una actuación sin título es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'fecha_limite' => 'fecha límite',
            'responsable_id' => 'responsable',
            'coste_estimado' => 'coste estimado',
        ];
    }
}
