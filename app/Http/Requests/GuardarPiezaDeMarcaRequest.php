<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Una pieza de marca de la organización.
 *
 * **Con SVG**, al revés que la foto de perfil, y la diferencia es real: allí es
 * la cara de alguien y no hay ningún motivo para admitir un documento XML; aquí
 * es un logo corporativo, que es justamente el formato en que existe, y va a
 * imprenta — un vector no se pixela a ningún tamaño.
 *
 * El SVG **no se admite tal cual**: lo pasa `GuardarPiezaDeMarca` por
 * `enshrined/svg-sanitize` antes de guardarlo. Y conviene tener claro que el
 * saneado es la **segunda** barrera: un SVG pintado como `background-image` o
 * como `<img>` no ejecuta scripts en ningún navegador, así que lo que el
 * saneador cubre es el día que alguien lo incruste en el DOM y el fichero raro
 * que llegue a Chromium al generar el PDF.
 *
 * `image` de Laravel no admite SVG por su cuenta, así que la regla de tipos es
 * `mimes` y no `image`.
 *
 * **2 MB**, menos que los 4 de una foto: esto acaba en base64 dentro del CSS de
 * cada documento que se genere, así que su peso se paga en cada PDF.
 */
class GuardarPiezaDeMarcaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pieza' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pieza.required' => 'Elige la imagen que quieres usar.',
            'pieza.mimes' => 'El logo tiene que ser SVG, PNG, JPG o WebP.',
            'pieza.max' => 'El logo no puede pasar de 2 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['pieza' => 'logo'];
    }
}
