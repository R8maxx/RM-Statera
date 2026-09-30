<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de un cambio del SGSI, desde su ficha.
 *
 * Qué transiciones exigen nota lo decide `EstadoCambio::exigeNota()`, y el
 * dominio lo vuelve a comprobar —`CambiarEstadoCambio`—, porque la regla vale
 * también para un importador; esto es para que el mensaje llegue al campo.
 */
class CambiarEstadoCambioSgsiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoCambio::class)],
            'nota' => [
                Rule::requiredIf(function (): bool {
                    $cambio = $this->route('cambio');
                    $destino = EstadoCambio::tryFrom((string) $this->input('estado'));

                    return $cambio instanceof CambioSgsi
                        && $destino !== null
                        && $destino->exigeNota($cambio->estado);
                }),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'Esta transición exige algo escrito: por qué se descarta, si el cambio sirvió o qué cambia al reabrirlo.',
        ];
    }
}
