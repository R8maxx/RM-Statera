<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular una tarea a un requisito.
 *
 * La pertenencia a la organización no se comprueba aquí: `exists` mira la tabla
 * entera, y quien recorta es el scope al resolver el modelo —404, nunca 403—.
 */
class VincularTareaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'implantacion_id' => ['required', 'integer', Rule::exists('implantaciones', 'id')],
        ];
    }
}
