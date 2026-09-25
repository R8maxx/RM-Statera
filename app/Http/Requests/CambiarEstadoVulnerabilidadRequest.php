<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mover una vulnerabilidad de estado. Si hace falta nota —motivo o
 * verificación— lo decide `CambiarEstadoVulnerabilidad`, que conoce el estado
 * de partida.
 */
class CambiarEstadoVulnerabilidadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoVulnerabilidad::class)],
            'nota' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
