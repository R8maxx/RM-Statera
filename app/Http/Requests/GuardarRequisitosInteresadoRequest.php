<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Contexto\Enums\NaturalezaRequisito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La lista entera de lo que exige o espera una parte interesada.
 *
 * Llega completa en una sola petición, como la lista de comprobación de una tarea:
 * lo que se está escribiendo es «qué nos pide este regulador», y eso se piensa de
 * una vez mirando la lista.
 *
 * **El `id` se valida como entero y no como `exists`.** Es a propósito: un
 * identificador que no sea de esta parte interesada lo trata
 * `GuardarRequisitosInteresado` como una línea nueva, que es más seguro que
 * rechazar la petición y mucho más seguro que aceptarlo como edición. Una regla
 * `exists` aquí daría por buena la fila de otra organización antes de que el
 * dominio pudiera decidir nada.
 */
class GuardarRequisitosInteresadoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requisitos' => ['present', 'array', 'max:100'],
            'requisitos.*.id' => ['nullable', 'integer'],
            'requisitos.*.descripcion' => ['required', 'string', 'max:2000'],
            'requisitos.*.naturaleza' => ['required', Rule::enum(NaturalezaRequisito::class)],
            'requisitos.*.es_climatico' => ['boolean'],
            'requisitos.*.referencia' => ['nullable', 'string', 'max:255'],
            'requisitos.*.como_se_atiende' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'requisitos.*.descripcion' => 'descripción del requisito',
            'requisitos.*.naturaleza' => 'naturaleza',
            'requisitos.*.referencia' => 'referencia',
            'requisitos.*.como_se_atiende' => 'cómo se atiende',
        ];
    }
}
