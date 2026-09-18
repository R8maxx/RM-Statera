<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La cabecera del análisis del contexto: su fecha, su nota y la declaración del
 * cambio climático.
 *
 * **Las dos columnas del clima viajan juntas y ninguna es obligatoria aquí.** El
 * borrador se puede guardar a medias —es donde se trabaja— y quien exige la
 * respuesta es `AprobarAnalisis`, que es el momento en que la declaración pasa a
 * ser algo que la organización firma. Pedirla desde el primer guardado obligaría a
 * contestar a la enmienda 1:2024 antes de haber escrito la primera cuestión.
 *
 * Lo que sí se impone es que **no se pueda marcar la casilla sin razonarla**: un
 * «sí, es pertinente» sin una línea detrás es lo que el auditor va a leer, y no
 * dice nada.
 */
class GuardarAnalisisContextoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_analisis' => ['required', 'date'],
            'nota' => ['nullable', 'string', 'max:5000'],
            'clima_pertinente' => ['nullable', 'boolean'],
            'clima_justificacion' => [
                'nullable', 'string', 'max:5000',
                /*
                 * Si hay respuesta, hay razonamiento. `required_with` y no
                 * `required_unless:…,null`: aquél compara contra la cadena
                 * «null» y nunca casaría con un nulo de verdad. Y cuenta como
                 * «hay respuesta» tanto el sí como el no, porque `false` pasa la
                 * regla `required` de Laravel — que es justo lo que hace falta
                 * aquí, donde «no es pertinente» es una respuesta y no una
                 * ausencia.
                 */
                'required_with:clima_pertinente',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clima_justificacion.required_with' => 'Razona la respuesta sobre el cambio climático: «no es pertinente» sin motivo no dice nada.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_analisis' => 'fecha del análisis',
            'nota' => 'nota',
            'clima_pertinente' => 'pertinencia del cambio climático',
            'clima_justificacion' => 'razonamiento sobre el cambio climático',
        ];
    }
}
