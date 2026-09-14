<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincula un control implantado como salvaguarda de un riesgo.
 *
 * Apunta a `implantaciones` y no a `requisitos`: lo que contiene un riesgo no es
 * que el ENS pida cifrado, es que nosotros lo tengamos implantado en este sistema.
 *
 * La nota es lo que el auditor lee cuando pregunta de dónde sale el residual, así
 * que se pide aunque sea opcional: «¿por qué este control cubre este riesgo?».
 */
class VincularSalvaguardaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'implantacion_id' => ['required', 'integer', Rule::exists('implantaciones', 'id')],
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'implantacion_id' => 'control',
        ];
    }
}
