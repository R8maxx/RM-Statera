<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La metodología de análisis de riesgos de la organización.
 *
 * **Aquí no se valida la coherencia de las escalas ni de los umbrales.** Eso lo
 * hacen `EscalaRiesgo` y `Metodologia`, que son por donde pasan también el seeder,
 * la fábrica y cualquier importador futuro. Lo que se comprueba aquí es la forma —
 * que llegue una lista de escalones con sus etiquetas y unos números—; que esos
 * números tengan sentido es una regla del dominio, y duplicarla la deja
 * desincronizándose a la primera.
 *
 * `aprobar` es una casilla y no un campo de texto: firmar es un acto de quien está
 * conectado, y dejar escribir el nombre del aprobador permitiría firmar en nombre
 * de otro.
 */
class GuardarMetodologiaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],

            'escala_probabilidad' => ['required', 'array', 'min:2', 'max:10'],
            'escala_probabilidad.*.valor' => ['required', 'integer', 'min:1', 'max:10'],
            'escala_probabilidad.*.etiqueta' => ['required', 'string', 'max:60'],
            'escala_probabilidad.*.descripcion' => ['nullable', 'string', 'max:500'],

            'escala_impacto' => ['required', 'array', 'min:2', 'max:10'],
            'escala_impacto.*.valor' => ['required', 'integer', 'min:1', 'max:10'],
            'escala_impacto.*.etiqueta' => ['required', 'string', 'max:60'],
            'escala_impacto.*.descripcion' => ['nullable', 'string', 'max:500'],

            'umbral_aceptacion' => ['required', 'integer', 'min:2', 'max:100'],
            'umbral_critico' => ['required', 'integer', 'min:2', 'max:100'],
            'periodicidad_revision_meses' => ['required', 'integer', 'min:1', 'max:60'],

            'notas' => ['nullable', 'string', 'max:5000'],

            'aprobar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'escala_probabilidad.min' => 'Una escala de menos de dos escalones no gradúa nada.',
            'escala_impacto.min' => 'Una escala de menos de dos escalones no gradúa nada.',
            'escala_probabilidad.*.etiqueta.required' => 'Cada escalón necesita etiqueta: sin ella en la tabla sale un número suelto.',
            'escala_impacto.*.etiqueta.required' => 'Cada escalón necesita etiqueta: sin ella en la tabla sale un número suelto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'escala_probabilidad' => 'escala de probabilidad',
            'escala_impacto' => 'escala de impacto',
            'umbral_aceptacion' => 'umbral de aceptación',
            'umbral_critico' => 'umbral crítico',
            'periodicidad_revision_meses' => 'periodicidad de reevaluación',
        ];
    }
}
