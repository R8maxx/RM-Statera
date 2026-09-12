<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tarea\Enums\EstadoTarea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Acción masiva de cambio de estado.
 *
 * `descartada` no está entre los valores admitidos, y no es un olvido: descartar
 * exige decir por qué, y un motivo escrito una vez para cincuenta tareas no es un
 * motivo, es un trámite. Se descartan de una en una, desde su ficha.
 */
class CambiarEstadoTareasRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tareas' => ['required', 'array', 'min:1', 'max:500'],
            'tareas.*' => ['integer', Rule::exists('tareas', 'id')],
            'estado' => [
                'required',
                Rule::enum(EstadoTarea::class)->only(array_filter(
                    EstadoTarea::cases(),
                    static fn (EstadoTarea $estado): bool => $estado !== EstadoTarea::Descartada,
                )),
            ],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }
}
