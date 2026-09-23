<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * El sellado de un cumplimiento.
 *
 * **No hay campo «estado».** El estado de un compromiso se deriva del último
 * cumplimiento y de la cadencia; admitirlo aquí dejaría el histórico contando una
 * cosa y el badge otra, que es la misma regla por la que marcar todos los pasos
 * de una checklist no cierra la tarea.
 *
 * **Una sola referencia.** La base lo impone con `num_nonnulls(...) <= 1` y aquí
 * se dice en castellano: un cumplimiento apunta a la auditoría que lo demuestra,
 * o al acta, o al documento, o a la prueba de continuidad realizada — no a
 * varias a la vez. La evidencia va aparte porque es otra cosa: es la prueba, y
 * convive con el registro.
 */
class RegistrarCumplimientoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * Las cinco referencias son opcionales y las cinco se pueden dejar en
     * blanco, así que las cinco mandan centinela.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['auditoria_id', 'revision_direccion_id', 'documento_id', 'prueba_continuidad_id', 'evidencia_id'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cubre_hasta' => ['nullable', 'date', 'after:fecha'],
            'auditoria_id' => ['nullable', 'integer', 'exists:auditorias,id'],
            'revision_direccion_id' => ['nullable', 'integer', 'exists:revisiones_direccion,id'],
            'documento_id' => ['nullable', 'integer', 'exists:documentos,id'],
            'prueba_continuidad_id' => ['nullable', 'integer', 'exists:pruebas_continuidad,id'],
            'evidencia_id' => ['nullable', 'integer', 'exists:evidencias,id'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'Un cumplimiento es un hecho: no se puede registrar con una fecha que todavía no ha llegado.',
            'cubre_hasta.after' => 'La cobertura tiene que ser posterior al cumplimiento, o el compromiso quedaría fuera de plazo el mismo día en que se cumplió.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                $referencias = array_filter([
                    $this->input('auditoria_id'),
                    $this->input('revision_direccion_id'),
                    $this->input('documento_id'),
                    $this->input('prueba_continuidad_id'),
                ]);

                if (count($referencias) > 1) {
                    $validador->errors()->add(
                        'auditoria_id',
                        'Un cumplimiento apunta a un registro, no a varios: la auditoría que lo demuestra, el acta, el documento o la prueba de continuidad.',
                    );
                }
            },
        ];
    }
}
