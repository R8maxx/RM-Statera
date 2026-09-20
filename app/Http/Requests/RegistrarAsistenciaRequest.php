<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La convocatoria de una sesión, guardada entera.
 *
 * Llega la lista de convocados con su asistencia, y **quien sale de la lista deja
 * de estar convocado**: no es lo mismo que haber faltado, y por eso una ruta que
 * marcara asistencias de una en una no valdría.
 */
class RegistrarAsistenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'convocadas' => ['present', 'array'],
            'convocadas.*.persona_id' => ['required', 'integer', Rule::exists('personas', 'id')],
            'convocadas.*.asistio' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Las personas convocadas, en la forma que espera `RegistrarAsistencia`.
     *
     * @return array<int, bool>
     */
    public function convocadas(): array
    {
        $convocadas = [];

        /** @var array<int, array{persona_id: int|string, asistio?: bool|null}> $filas */
        $filas = $this->validated('convocadas', []);

        foreach ($filas as $fila) {
            $convocadas[(int) $fila['persona_id']] = (bool) ($fila['asistio'] ?? false);
        }

        return $convocadas;
    }
}
