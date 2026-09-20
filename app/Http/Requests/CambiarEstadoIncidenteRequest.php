<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Incidente\Enums\EstadoIncidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un paso del ciclo de un incidente.
 *
 * **La nota no es obligatoria aquí y sí en el dominio para algunas
 * transiciones**, y es el reparto de siempre: qué transiciones la exigen depende
 * del estado de partida, que el `FormRequest` no tiene por qué saber, y la regla
 * vale igual para un importador. `CambiarEstadoIncidente` es quien manda.
 */
class CambiarEstadoIncidenteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoIncidente::class)],
            'nota' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
