<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Asignar un puesto a una persona.
 *
 * `desde` va **sin `after_or_equal:today`**, igual que la fecha de un
 * nombramiento: lo normal al meter el histórico es registrar una asignación que
 * empezó hace tres años.
 *
 * Que la persona esté de baja **no se valida aquí**: lo rechaza `AsignarPuesto`,
 * porque la regla vale igual para un importador y para el seeder.
 */
class AsignarPuestoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Sin acotar a la organización: lo tapa RLS, que es justo el caso
            // para el que existe la tercera capa. Mismo criterio que
            // `VincularEvidenciaRequest`.
            'puesto_id' => ['required', 'integer', Rule::exists('puestos', 'id')],
            'desde' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'puesto_id' => 'puesto',
            'desde' => 'fecha de inicio',
        ];
    }
}
