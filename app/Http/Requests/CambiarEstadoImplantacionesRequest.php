<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Acción masiva de cambio de estado.
 *
 * `no_aplica` no está entre los valores admitidos: lo deriva el motor tras un
 * recálculo de la valoración, nunca una persona.
 */
class CambiarEstadoImplantacionesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'implantaciones' => ['required', 'array', 'min:1', 'max:500'],
            'implantaciones.*' => ['integer', Rule::exists('implantaciones', 'id')],
            'estado' => [
                'required',
                Rule::enum(EstadoImplantacion::class)
                    ->only(array_filter(
                        EstadoImplantacion::cases(),
                        static fn (EstadoImplantacion $estado): bool => $estado->esGestionablePorUsuario(),
                    )),
            ],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }
}
