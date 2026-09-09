<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vincular una evidencia con un requisito.
 *
 * `exists` no acota por organización y no hace falta que lo haga: la regla
 * consulta la base directamente, y ahí manda la política de Row Level Security.
 * Una evidencia de otra organización sencillamente no está, así que la
 * validación falla con «no existe» —que desde este tenant es literalmente
 * cierto— en lugar de filtrar que existe y no es suya.
 */
class VincularEvidenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evidencia_id' => ['required', 'integer', Rule::exists('evidencias', 'id')],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['evidencia_id' => 'evidencia'];
    }
}
