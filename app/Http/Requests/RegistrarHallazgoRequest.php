<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un hallazgo: lo que el auditor encontró.
 *
 * `auditoria_punto_id` es opcional **a propósito**, y no por descuido: el
 * hallazgo que no cuelga de ninguna medida —sobre el sistema de gestión— es un
 * caso normal de una auditoría ISO. Que el punto sea de esta auditoría lo
 * comprueba la ruta con `scopeBindings()`, no una regla de aquí.
 */
class RegistrarHallazgoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoHallazgo::class)],
            'descripcion' => ['required', 'string', 'max:5000'],
            'auditoria_punto_id' => ['nullable', 'integer', 'exists:auditoria_puntos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['auditoria_punto_id' => 'medida'];
    }
}
