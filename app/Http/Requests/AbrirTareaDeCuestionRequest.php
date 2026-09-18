<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La tarea que se abre desde la ficha de una cuestión del contexto.
 *
 * Subconjunto de `GuardarTareaRequest`, **sin `origen` a propósito**: lo pone
 * `AbrirTareaDeCuestion` y no se pregunta, igual que en la acción correctiva y en
 * el camino corto desde una implantación. Preguntarlo invita a cambiarlo, y una
 * tarea marcada «iniciativa propia» pierde lo único que explicaba por qué se está
 * haciendo.
 */
class AbrirTareaDeCuestionRequest extends FormRequest
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
            // Sin `after_or_equal:today`, como en tareas: lo que se apunta tarde
            // lleva la fecha que le tocaba, y falsearla para que el formulario la
            // acepte es peor que verla vencida desde el primer día.
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
            'titulo.required' => 'Una tarea sin título es una fila que nadie sabe qué es.',
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
