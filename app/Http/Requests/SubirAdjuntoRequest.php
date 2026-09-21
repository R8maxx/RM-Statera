<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Subir un documento a un registro.
 *
 * **Sin lista de `mimes`**, igual que una evidencia: lo que hay que adjuntar a
 * una persona no lo decide esta herramienta —un título viene en PDF, una hoja de
 * firmas escaneada en JPG y un certificado de un proveedor en cualquier cosa— y
 * una lista blanca corta acaba en gente renombrando extensiones, que es peor.
 * El bucket es privado y la descarga va siempre por URL firmada con
 * `Content-Disposition: attachment`, así que nada se sirve en línea.
 *
 * El tope son 50 MB, el mismo que las evidencias.
 */
class SubirAdjuntoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fichero' => ['required', 'file', 'max:51200'],
            'titulo' => ['required', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichero.max' => 'El documento no puede pasar de 50 MB.',
            'fichero.required' => 'Elige el documento que quieres subir.',
        ];
    }
}
