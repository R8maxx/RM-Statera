<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El acuerdo de confidencialidad de una persona: `mp.per.2`.
 *
 * **`vigente_hasta` es opcional y su vacío significa algo**: un acuerdo de
 * confidencialidad normalmente no vence —el deber sobrevive a la relación
 * laboral—, así que dejarlo en blanco es la respuesta correcta y no un descuido.
 */
class GuardarAcuerdoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_firma' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:fecha_firma'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'evidencia_id' => ['nullable', 'integer', Rule::exists('evidencias', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vigente_hasta.after_or_equal' => 'Un acuerdo no puede caducar antes de firmarse.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fecha_firma' => 'fecha de firma', 'evidencia_id' => 'documento firmado'];
    }

    /**
     * La evidencia es opcional y su desplegable manda el centinela de
     * «ninguno», así que hay que traducirlo antes de validar.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['evidencia_id'];
    }
}
