<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los tres vínculos del módulo: un riesgo a una cuestión, una tarea a una cuestión
 * y una implantación a un requisito de una parte interesada.
 *
 * **Uno solo para los tres**, porque los tres piden exactamente lo mismo: un
 * identificador. Tres clases con una sola regla distinta es cómo se acaba con una
 * que valida y otra que no.
 *
 * **`exists` no lleva `where organizacion_id`, a diferencia de los desplegables de
 * usuarios.** No hace falta y sería engañoso: `riesgos`, `tareas` e
 * `implantaciones` sí llevan `PerteneceAOrganizacion` y RLS, así que el
 * identificador de otro cliente ya es invisible para la consulta. Quien de verdad
 * cierra la puerta es el controlador, que resuelve **por el modelo**
 * —`Riesgo::query()->findOrFail()`— y no copiando el entero: así pasa por el scope
 * y por RLS y lo ajeno da 404. `User` es la excepción del producto porque no lleva
 * ninguna de las tres capas.
 */
class VincularEnContextoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'riesgo_id' => ['nullable', 'integer', 'exists:riesgos,id'],
            'tarea_id' => ['nullable', 'integer', 'exists:tareas,id'],
            'implantacion_id' => ['nullable', 'integer', 'exists:implantaciones,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'riesgo_id' => 'riesgo',
            'tarea_id' => 'tarea',
            'implantacion_id' => 'implantación',
        ];
    }
}
