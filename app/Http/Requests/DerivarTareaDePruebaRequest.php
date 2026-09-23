<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\PrioridadTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El trabajo correctivo que se abre desde la ficha de una prueba de continuidad.
 *
 * Es un subconjunto de `GuardarTareaRequest` **y le falta `origen` a propósito**,
 * calcando `AbrirAccionCorrectivaRequest`: lo pone `DerivarDePrueba` y no se
 * pregunta, porque una tarea marcada «iniciativa propia» pierde lo único que la
 * hacía trazable.
 */
class DerivarTareaDePruebaRequest extends FormRequest
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
            // Sin `after_or_equal:today`, como en el resto de la familia: una
            // tarea que se apunta tarde lleva la fecha que le tocaba.
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
