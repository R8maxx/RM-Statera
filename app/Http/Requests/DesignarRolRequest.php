<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Persona\Enums\RolEns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Designar a una persona en un rol ENS de un sistema. Cláusula 5.3.
 *
 * **La incompatibilidad no se valida aquí**, y es deliberado: vive en
 * `DesignarRol` porque vale igual para un importador y para el seeder, y porque
 * es una condición entre filas que ninguna regla de validación expresa bien. Lo
 * que llega desde aquí es el mensaje al campo cuando el dominio la rechaza.
 *
 * La pertenencia del sistema a la organización no se comprueba: `exists` mira la
 * tabla entera y quien recorta es el scope al resolver el modelo —404, nunca 403—.
 */
class DesignarRolRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sistema_id' => ['required', 'integer', Rule::exists('sistemas', 'id')],
            'rol' => ['required', Rule::enum(RolEns::class)],
            // Sin `after_or_equal:today`: un nombramiento se registra en Statera
            // semanas después de firmarse, y falsear la fecha para que el
            // formulario la acepte es peor que verlo con su fecha real.
            'desde' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sistema_id' => 'sistema', 'desde' => 'fecha de designación'];
    }
}
