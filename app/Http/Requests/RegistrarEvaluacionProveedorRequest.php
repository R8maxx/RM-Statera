<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Proveedor\Enums\ResultadoClausula;
use App\Domain\Proveedor\Enums\ResultadoEvaluacion;
use App\Domain\Proveedor\Models\ClausulaContractual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Registrar la evaluación del contrato de un proveedor.
 *
 * Todas las cláusulas vigentes llevan respuesta: se comprueba aquí para que el
 * error salga junto a la que falta, y otra vez en `RegistrarEvaluacion`, que es
 * donde vive la regla.
 */
class RegistrarEvaluacionProveedorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'resultado' => ['required', Rule::enum(ResultadoEvaluacion::class)],
            'conclusiones' => [
                Rule::requiredIf(fn (): bool => $this->input('resultado') !== ResultadoEvaluacion::Apto->value),
                'nullable',
                'string',
                'max:5000',
            ],
            'clausulas' => ['required', 'array'],
        ];

        foreach (ClausulaContractual::query()->vigentes()->pluck('id') as $id) {
            $reglas["clausulas.{$id}.resultado"] = ['required', Rule::enum(ResultadoClausula::class)];
            $reglas["clausulas.{$id}.nota"] = ['nullable', 'string', 'max:2000'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conclusiones.required' => 'Si no es apto sin más, hay que escribir qué falla o qué condiciones se ponen.',
            'clausulas.*.resultado.required' => 'Falta contestar esta cláusula: cumple, no cumple o no aplica.',
            'fecha.before_or_equal' => 'Una evaluación se registra cuando ya se ha hecho.',
        ];
    }

    public function fecha(): Carbon
    {
        return Carbon::parse((string) $this->validated('fecha'))->startOfDay();
    }

    public function resultado(): ResultadoEvaluacion
    {
        return ResultadoEvaluacion::from((string) $this->validated('resultado'));
    }

    /**
     * @return array<int, array{resultado: ResultadoClausula, nota: ?string}>
     */
    public function clausulas(): array
    {
        /** @var array<int|string, array{resultado: string, nota?: ?string}> $crudas */
        $crudas = $this->validated('clausulas');
        $clausulas = [];

        foreach ($crudas as $id => $respuesta) {
            $clausulas[(int) $id] = [
                'resultado' => ResultadoClausula::from($respuesta['resultado']),
                'nota' => isset($respuesta['nota']) && trim((string) $respuesta['nota']) !== '' ? (string) $respuesta['nota'] : null,
            ];
        }

        return $clausulas;
    }
}
