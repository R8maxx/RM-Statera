<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular un servicio que ya existe a un plan de continuidad.
 *
 * El tipo del activo se comprueba aquí y otra vez en `VincularServicioAPlan`:
 * la del `FormRequest` es la que convierte el error en un mensaje bajo el
 * campo; la del dominio es la que vale también para lo que llegue por un
 * importador el día que exista uno.
 */
class VincularServicioAPlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activo_id' => [
                'required', 'integer',
                Rule::exists('activos', 'id')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->where('tipo', TipoActivo::Servicios->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['activo_id' => 'servicio'];
    }
}
