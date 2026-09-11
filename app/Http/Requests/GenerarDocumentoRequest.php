<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Qué se pide al generar.
 *
 * Se guardan en `documento_versiones.parametros`, aparte de la instantánea:
 * aquello es lo que salió, esto es lo que se pidió, y no son la misma pregunta.
 */
class GenerarDocumentoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incluir_evidencias' => ['sometimes', 'boolean'],
            'incluir_mapeo_cruzado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parametros(): array
    {
        return [
            'incluir_evidencias' => $this->boolean('incluir_evidencias', true),
            'incluir_mapeo_cruzado' => $this->boolean('incluir_mapeo_cruzado', true),
        ];
    }
}
