<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular una tarea que ya existe como acción correctiva de una no conformidad.
 *
 * La pertenencia a la organización no se comprueba aquí: `exists` mira la tabla
 * entera, y quien recorta es el scope al resolver el modelo —404, nunca 403—.
 */
class VincularAccionCorrectivaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tarea_id' => ['required', 'integer', Rule::exists('tareas', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tarea_id' => 'tarea'];
    }
}
