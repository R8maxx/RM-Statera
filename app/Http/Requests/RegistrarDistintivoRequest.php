<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dónde y desde cuándo está publicado el distintivo de conformidad.
 *
 * El dominio vuelve a comprobar la URL y la fecha (`RegistrarPublicacionDistintivo`),
 * porque la regla vale también para un importador; esto es para que el mensaje
 * llegue al campo.
 */
class RegistrarDistintivoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'distintivo_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'distintivo_publicado_en' => ['required', 'date', 'before_or_equal:today'],
            'distintivo_evidencia_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'distintivo_url.required' => 'Indica la dirección de la página donde está publicado el distintivo.',
            'distintivo_url.url' => 'La dirección tiene que ser una URL pública, que empiece por http:// o https://.',
            'distintivo_publicado_en.before_or_equal' => 'El distintivo no puede estar publicado en una fecha futura.',
        ];
    }
}
