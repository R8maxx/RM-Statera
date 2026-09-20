<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La lección aprendida de un incidente, guardada por su cuenta.
 *
 * **Ruta propia y no un campo más del formulario largo**, y es la decisión que
 * define el módulo: `op.exp.7` pide aprender del incidente, y ese texto se
 * escribe **mientras se resuelve** —a trozos, según se va sabiendo—, no el día
 * que se dio de alta. Obligar a abrir el formulario entero para añadir una línea
 * es cómo se consigue que esa línea no se escriba.
 *
 * Admite el vacío: se puede borrar mientras el incidente no esté cerrado, y a
 * partir de ahí lo impide el trigger de coherencia — el `CHECK`
 * `incidentes_leccion_check`.
 */
class GuardarLeccionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leccion_aprendida' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
