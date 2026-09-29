<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Persona\Enums\JustificacionAusencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La convocatoria de una sesión, guardada entera.
 *
 * Llega la lista de convocados con su asistencia, y **quien sale de la lista deja
 * de estar convocado**: no es lo mismo que haber faltado, y por eso una ruta que
 * marcara asistencias de una en una no valdría.
 *
 * Quien faltó puede traer además **por qué**: justificada, con su motivo, o sin
 * justificar. Sin nada es «sin indicar», que no es ninguna de las dos.
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
            'convocadas.*.ausencia' => ['nullable', Rule::enum(JustificacionAusencia::class)],
            /*
             * Una ausencia justificada sin motivo no justifica nada: es la misma
             * marca que «sin justificar» con otra etiqueta. En la base puede
             * faltar —la supresión de una persona lo vacía—, así que se exige
             * aquí, al guardar, y no en un `CHECK`.
             */
            'convocadas.*.motivo' => ['nullable', 'string', 'max:500', 'required_if:convocadas.*.ausencia,justificada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'convocadas.*.motivo.required_if' => 'Una ausencia justificada necesita su motivo: escríbelo o márcala como sin justificar.',
            'convocadas.*.motivo.max' => 'El motivo de una ausencia no puede pasar de 500 caracteres.',
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

        foreach ($this->filas() as $fila) {
            $convocadas[(int) $fila['persona_id']] = (bool) ($fila['asistio'] ?? false);
        }

        return $convocadas;
    }

    /**
     * Por qué faltó cada una, en la forma que espera `RegistrarAsistencia`.
     *
     * @return array<int, array{ausencia: ?JustificacionAusencia, motivo: ?string}>
     */
    public function ausencias(): array
    {
        $ausencias = [];

        foreach ($this->filas() as $fila) {
            $motivo = trim((string) ($fila['motivo'] ?? ''));

            $ausencias[(int) $fila['persona_id']] = [
                'ausencia' => JustificacionAusencia::tryFrom((string) ($fila['ausencia'] ?? '')),
                'motivo' => $motivo === '' ? null : $motivo,
            ];
        }

        return $ausencias;
    }

    /**
     * @return array<int, array{persona_id: int|string, asistio?: bool|null, ausencia?: string|null, motivo?: string|null}>
     */
    private function filas(): array
    {
        /** @var array<int, array{persona_id: int|string, asistio?: bool|null, ausencia?: string|null, motivo?: string|null}> $filas */
        $filas = $this->validated('convocadas', []);

        return $filas;
    }
}
