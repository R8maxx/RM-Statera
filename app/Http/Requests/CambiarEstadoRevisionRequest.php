<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Empezar una revisión, o reabrirla.
 *
 * **`aprobada` no se admite aquí**, y lo vuelve a rechazar el dominio: aprobar no
 * es un cambio de estado, es el acto que congela las siete entradas de la 9.3.2 y
 * estampa la firma. Tiene su propia ruta y su propio permiso.
 */
class CambiarEstadoRevisionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::enum(EstadoRevision::class)->except([EstadoRevision::Aprobada]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.Illuminate\Validation\Rules\Enum' => 'Aprobar el acta no es un cambio de estado: se firma desde su propio botón.',
        ];
    }
}
