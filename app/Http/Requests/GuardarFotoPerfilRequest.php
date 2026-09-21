<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La foto de perfil de la propia cuenta.
 *
 * **Aquí SÍ hay lista blanca**, al revés que en `SubirAdjuntoRequest`, y la
 * diferencia es real: un adjunto es «lo que haya que adjuntar» y esto es una
 * imagen que **vamos a decodificar nosotros** con GD para recortarla y
 * convertirla. Admitir cualquier cosa significa pasarle a GD un fichero que no
 * sabe leer, y lo que ve quien lo sube es un 500 en vez de «esto no es una
 * imagen».
 *
 * Tres formatos y no más: son los que un navegador produce al elegir una foto y
 * los tres que GD lee y escribe en esta imagen. Sin SVG a propósito —es un
 * documento con scripts dentro, no un mapa de bits— y sin HEIC, que es lo que
 * da un iPhone por defecto y que GD no lee: quien lo intente recibe el mensaje
 * de abajo, que es mejor que un fallo de decodificación.
 *
 * **4 MB y no los 50 de un adjunto.** Lo que se guarda son 256 px; el tope de
 * entrada sólo tiene que dejar pasar una foto de móvil, y cuanto más alto sea
 * más tiempo pasa GD decodificando algo que va a tirar.
 *
 * No hay autorización que declarar: se escribe sobre uno mismo, como el resto
 * de `/perfil` y como el acuse de lectura de un documento.
 */
class GuardarFotoPerfilRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Elige la imagen que quieres usar.',
            'foto.image' => 'Eso no es una imagen.',
            'foto.mimes' => 'La foto tiene que ser JPG, PNG o WebP.',
            'foto.max' => 'La foto no puede pasar de 4 MB.',
        ];
    }
}
