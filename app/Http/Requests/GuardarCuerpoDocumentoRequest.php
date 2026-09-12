<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El cuerpo que llega del editor.
 *
 * Aquí se valida la **forma** —que sea un documento de ProseMirror y no exceda
 * el tamaño—, no el contenido: de eso responde `SanearCuerpo`, contra el
 * esquema, que es donde vive la lista blanca. Escribir aquí un `array` de reglas
 * anidadas sería mantener el esquema en un tercer sitio.
 *
 * `actualizado_en` es el control de concurrencia. Gana el último, como en el
 * resto del producto, pero **se avisa**: dos personas editando el mismo
 * documento a la vez es lo normal el día antes de una auditoría, y perder media
 * hora de redacción en silencio es lo que hace que la gente vuelva al Word.
 */
class GuardarCuerpoDocumentoRequest extends FormRequest
{
    /**
     * El tope de tamaño.
     *
     * Una SoA de noventa y tres controles ronda los trescientos kilobytes de
     * JSON; dos megas deja sitio de sobra para un documento largo y corta el
     * caso de alguien pegando un libro entero, que acabaría en una instantánea
     * por versión.
     */
    public const MAX_BYTES = 2_000_000;

    public function authorize(): bool
    {
        // Quien puede entrar en la ruta ya pasó por `can:documentos.redactar` y
        // por el segundo factor. La pertenencia a la organización la resuelven
        // el scope y RLS, que responden 404 y no 403.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cuerpo' => ['required', 'array'],
            'cuerpo.type' => ['required', 'string', 'in:doc'],
            'cuerpo.content' => ['required', 'array', 'min:1'],
            'actualizado_en' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuerpo.type' => 'Lo que se ha enviado no es un documento.',
            'cuerpo.content.required' => 'Un documento no puede quedarse vacío.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $cuerpo = $this->input('cuerpo');

        // Se mide antes de validar: un cuerpo de veinte megas no debe llegar a
        // recorrerse nodo a nodo sólo para rechazarlo después.
        if (is_array($cuerpo) && strlen((string) json_encode($cuerpo)) > self::MAX_BYTES) {
            abort(413, 'El documento es demasiado grande para guardarse.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cuerpo(): array
    {
        $cuerpo = $this->validated('cuerpo');

        return is_array($cuerpo) ? $cuerpo : [];
    }
}
