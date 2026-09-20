<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular un indicador que ya existe como criterio de evaluación de un objetivo
 * (6.2, planificación e).
 *
 * **No crea indicadores**, y por eso aquí sólo llega un id: fabricar uno desde el
 * formulario del objetivo produciría indicadores sin periodicidad, sin
 * responsable y sin método, que es justo lo que la 9.1 pide.
 *
 * La pertenencia a la organización no se comprueba aquí: `exists` mira la tabla
 * entera, y quien recorta es el scope al resolver el modelo —404, nunca 403—.
 */
class VincularIndicadorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'indicador_id' => ['required', 'integer', Rule::exists('indicadores', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['indicador_id' => 'indicador'];
    }
}
