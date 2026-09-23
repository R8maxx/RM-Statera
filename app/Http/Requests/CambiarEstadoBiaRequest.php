<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Continuidad\Enums\EstadoBia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un paso del ciclo de un BIA.
 *
 * **La nota no es obligatoria aquí y sí en el dominio para algunas
 * transiciones**, igual que en incidentes: qué transición la exige depende del
 * estado de partida, que el `FormRequest` no tiene por qué saber, y la regla
 * vale igual para un importador. `CambiarEstadoBia` es quien manda.
 */
class CambiarEstadoBiaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoBia::class)],
            'nota' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
