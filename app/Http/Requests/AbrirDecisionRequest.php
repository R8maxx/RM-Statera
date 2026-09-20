<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La decisión que se registra desde el acta de una revisión (9.3.3).
 *
 * Es un subconjunto de `GuardarTareaRequest` **y le falta `origen` a propósito**:
 * lo pone `AbrirDecision`, y es `OrigenTarea::RevisionDireccion` — el valor que
 * llevaba desde la primera migración declarado y sin ofrecerse, esperando
 * justamente a este módulo.
 */
class AbrirDecisionRequest extends FormRequest
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
            'titulo.required' => 'Una decisión sin enunciado es una línea de acta que nadie puede comprobar el año que viene.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'decisión',
            'fecha_limite' => 'fecha límite',
            'responsable_id' => 'responsable',
            'coste_estimado' => 'coste estimado',
        ];
    }
}
