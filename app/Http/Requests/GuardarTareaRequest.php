<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de una tarea.
 *
 * **El estado no se toca aquí.** Se cambia por su propia ruta, que es la que
 * registra la transición y ajusta la fecha de cierre; dejarlo entrar por el
 * formulario permitiría cerrar una tarea sin dejar rastro de cuándo ni por qué,
 * que es justo lo que el invariante 7 prohíbe.
 *
 * `origen` sólo admite los que hoy existen: los otros cuatro están declarados
 * —el modelo entero desde el principio— pero no se pueden elegir hasta que su
 * módulo llegue, porque una tarea marcada como «hallazgo de auditoría» sin
 * auditoría detrás no es trazable, es una etiqueta.
 */
class GuardarTareaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'origen' => [
                'required',
                Rule::enum(OrigenTarea::class)->only(OrigenTarea::disponibles()),
            ],
            'prioridad' => ['required', Rule::enum(PrioridadTarea::class)],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            // Sin `after_or_equal:today`: una tarea que se apunta tarde lleva la
            // fecha que le tocaba, y falsearla para que el formulario la acepte
            // es peor que verla vencida desde el primer día.
            'fecha_limite' => ['nullable', 'date'],
            'coste_estimado' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notas' => ['nullable', 'string', 'max:5000'],

            // Los requisitos que hace avanzar, si se crea desde una ficha.
            'implantaciones' => ['sometimes', 'array'],
            'implantaciones.*' => ['integer', Rule::exists('implantaciones', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origen.required' => 'Di de dónde sale la tarea: es lo que la hace trazable.',
            'origen.Illuminate\Validation\Rules\Enum' => 'Ese origen todavía no se puede usar: su módulo no existe.',
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

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }
}
