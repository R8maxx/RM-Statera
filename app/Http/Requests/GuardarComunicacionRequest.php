<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que se comunicó o se recibió, en el alta y en la edición.
 *
 * **En la edición no se mueven ni el sentido, ni la fecha, ni la previsión**:
 * de los tres cuelga el `cubre_hasta` congelado, y moverlos repintaría el plan.
 * Si se apuntó mal, se borra y se registra otra vez — el mismo criterio que un
 * cumplimiento de compromiso.
 *
 * La pertenencia de la parte interesada, la previsión y la evidencia no se
 * comprueban aquí: las tres tablas tienen RLS y la validación pasa por ella.
 */
class GuardarComunicacionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $edicion = $this->route('comunicacion') instanceof Comunicacion;
        $sentido = $this->sentido();

        return [
            'sentido' => [Rule::requiredIf(! $edicion), Rule::enum(SentidoComunicacion::class)],
            'fecha' => [Rule::requiredIf(! $edicion), 'date', 'before_or_equal:today'],
            'comunicacion_prevista_id' => ['nullable', 'integer', Rule::exists('comunicaciones_previstas', 'id')],
            'asunto' => ['required', 'string', 'max:255'],
            'resumen' => ['nullable', 'string', 'max:5000'],
            'canal' => ['nullable', Rule::enum(CanalComunicacion::class)],
            'parte_interesada_id' => ['nullable', 'integer', Rule::exists('partes_interesadas', 'id')],
            'tipo_recibida' => [
                Rule::requiredIf($sentido === SentidoComunicacion::Recibida),
                Rule::prohibitedIf($sentido === SentidoComunicacion::Emitida),
                'nullable',
                Rule::enum(TipoRetroalimentacion::class),
            ],
            'respuesta' => [
                Rule::prohibitedIf($sentido === SentidoComunicacion::Emitida),
                'nullable',
                'string',
                'max:5000',
            ],
            'evidencia_id' => ['nullable', 'integer', Rule::exists('evidencias', 'id')],
        ];
    }

    /**
     * El sentido que manda: el de la fila en la edición, el del formulario en el
     * alta.
     */
    public function sentido(): ?SentidoComunicacion
    {
        $comunicacion = $this->route('comunicacion');

        return $comunicacion instanceof Comunicacion
            ? $comunicacion->sentido
            : SentidoComunicacion::tryFrom((string) $this->input('sentido'));
    }

    /**
     * Lo que se puede tocar en la edición. Ver la cabecera.
     *
     * @return array<string, mixed>
     */
    public function editables(): array
    {
        return $this->safe()->except(['sentido', 'fecha', 'comunicacion_prevista_id']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'Una comunicación es un hecho: no se puede registrar con una fecha que todavía no ha llegado.',
            'tipo_recibida.required' => 'Lo recibido necesita decir qué es: una queja, una sugerencia, el resultado de una encuesta…',
            'asunto.required' => 'Una comunicación sin asunto es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comunicacion_prevista_id' => 'comunicación prevista',
            'parte_interesada_id' => 'parte interesada',
            'tipo_recibida' => 'tipo',
            'evidencia_id' => 'evidencia',
        ];
    }

    /** @return list<string> */
    protected function seleccionesOpcionales(): array
    {
        return ['comunicacion_prevista_id', 'parte_interesada_id', 'evidencia_id', 'canal', 'tipo_recibida'];
    }
}
