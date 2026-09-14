<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Una evaluación de un riesgo.
 *
 * **Los topes salen de la metodología de la organización, no de un número escrito
 * aquí.** Una escala de diez escalones tiene que poder usar el diez, y una de tres
 * no debe aceptar el cuatro. Escribir `max:5` sería atar la validación a la escala
 * de fábrica y romper a la primera organización que cambie la suya.
 *
 * El residual es opcional, pero **entero o nada**: media declaración no se puede
 * multiplicar. Y no puede superar al intrínseco, porque una salvaguarda no empeora
 * un riesgo.
 */
class ValorarRiesgoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $metodologia = app(MetodologiaVigente::class)->para();

        return [
            'probabilidad' => ['required', 'integer', 'min:1', 'max:'.$metodologia->probabilidad->maximo()],
            'impacto' => ['required', 'integer', 'min:1', 'max:'.$metodologia->impacto->maximo()],

            'probabilidad_residual' => ['nullable', 'integer', 'min:1', 'max:'.$metodologia->probabilidad->maximo()],
            'impacto_residual' => ['nullable', 'integer', 'min:1', 'max:'.$metodologia->impacto->maximo()],
            'justificacion_residual' => ['nullable', 'string', 'max:5000'],

            'decision' => ['required', Rule::enum(DecisionRiesgo::class)],
            'nota' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $probabilidad = $this->integer('probabilidad_residual');
            $impacto = $this->integer('impacto_residual');

            $unaSi = $this->filled('probabilidad_residual') !== $this->filled('impacto_residual');

            if ($unaSi) {
                $validator->errors()->add(
                    'probabilidad_residual',
                    'El riesgo residual necesita sus dos factores: con uno solo no hay nada que multiplicar.',
                );

                return;
            }

            if (! $this->filled('probabilidad_residual')) {
                return;
            }

            if ($probabilidad * $impacto > $this->integer('probabilidad') * $this->integer('impacto')) {
                $validator->errors()->add(
                    'impacto_residual',
                    'El riesgo residual no puede superar al intrínseco: una salvaguarda no empeora un riesgo.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Di qué se va a hacer con el riesgo: mitigar, aceptar, transferir o evitar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'probabilidad_residual' => 'probabilidad residual',
            'impacto_residual' => 'impacto residual',
            'justificacion_residual' => 'justificación del residual',
            'decision' => 'decisión',
        ];
    }

    /**
     * Los dos factores del residual son desplegables opcionales: «todavía no lo he
     * decidido» tiene que poder elegirse, y Reka no admite un valor vacío.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['probabilidad_residual', 'impacto_residual'];
    }
}
