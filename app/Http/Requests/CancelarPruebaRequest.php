<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancela una prueba de continuidad planificada.
 *
 * **`motivo` es obligatorio aquí y no sólo en el dominio.** A diferencia de
 * `CambiarEstadoBiaRequest`, cancelar es la única puerta que existe para este
 * paso —no hay un `estado` genérico que decida si hace falta nota—, así que el
 * `FormRequest` puede exigirlo sin tener que conocer el estado de partida.
 * `CancelarPrueba` lo vuelve a comprobar igualmente: la regla vale también
 * para lo que llegue sin pasar por este formulario.
 */
class CancelarPruebaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }
}
