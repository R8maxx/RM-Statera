<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La dirección lo ha mirado y ha dicho que no.
 *
 * **El motivo es obligatorio**, igual que al descartar una tarea: rechazar es una
 * decisión, y una decisión sin motivo escrito no se puede auditar ni retomar.
 * Quien recoja el documento dentro de tres semanas tiene que saber qué hay que
 * cambiar.
 *
 * La regla vive además en `RechazarVersion`, porque vale también para un
 * importador o para un comando: el `FormRequest` es la única fuente de verdad de
 * la validación de una petición, no de las reglas del dominio.
 */
class RechazarVersionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Di por qué se rechaza: quien retome el documento necesita saber qué cambiar.',
        ];
    }
}
