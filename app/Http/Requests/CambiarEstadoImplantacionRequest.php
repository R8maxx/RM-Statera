<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de una sola implantación, desde su ficha.
 *
 * `no_aplica` no está entre los valores admitidos, igual que en la acción
 * masiva: lo deriva el motor tras un recálculo, o lo pone una exclusión
 * motivada, que es otro camino con su propia validación.
 */
class CambiarEstadoImplantacionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
