<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Soporte\VentanaSoporte;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuánto tiempo abre el cliente la puerta a la plataforma (punto 44): entre una
 * hora y siete días. Se cierra sola al pasar.
 */
class AbrirSoporteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'horas' => ['required', 'integer', 'min:'.VentanaSoporte::HORAS_MINIMAS, 'max:'.VentanaSoporte::HORAS_MAXIMAS],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'horas.min' => 'El acceso de soporte dura al menos una hora.',
            'horas.max' => 'El acceso de soporte dura como mucho siete días.',
        ];
    }

    public function horas(): int
    {
        return (int) $this->validated('horas');
    }
}
