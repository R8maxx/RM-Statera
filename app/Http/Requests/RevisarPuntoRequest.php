<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marcar una línea de la checklist.
 *
 * `resultado` admite los cinco casos, incluido `pendiente`: volver a «sin
 * revisar» es lo que se hace cuando alguien marca la fila equivocada, y sin esa
 * vuelta atrás la única salida sería marcarla de cualquier otra cosa.
 */
class RevisarPuntoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::enum(ResultadoPunto::class)],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
