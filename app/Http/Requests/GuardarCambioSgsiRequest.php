<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\OrigenCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un cambio del SGSI. Cláusula 6.3.
 *
 * **El estado no está entre las reglas**: se mueve por su ruta de transición, que
 * es la que pone la firma, las fechas y el histórico.
 *
 * **Y la fecha prevista es opcional al escribir**, obligatoria al aprobar: la
 * misma regla que en objetivos. Exigirla en el borrador impediría apuntar el
 * cambio el día que se decide.
 */
class GuardarCambioSgsiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cambio = $this->route('cambio');
        $id = $cambio instanceof CambioSgsi ? $cambio->id : null;
        $organizacion = app(ContextoOrganizacion::class)->idObligatorio();

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('cambios_sgsi', 'codigo')->where('organizacion_id', $organizacion)->ignore($id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'ambito' => ['required', Rule::enum(AmbitoCambio::class)],
            'origen' => ['required', Rule::enum(OrigenCambio::class)],

            'proposito' => ['nullable', 'string', 'max:5000'],
            'consecuencias' => ['nullable', 'string', 'max:5000'],
            'integridad' => ['nullable', 'string', 'max:5000'],
            'recursos' => ['nullable', 'string', 'max:5000'],

            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $organizacion),
            ],
            'fecha_propuesta' => ['required', 'date'],
            'fecha_prevista' => [
                // Un cambio ya aprobado no puede quedarse sin plazo por una
                // edición: el `CHECK` lo rechazaría con un error que no dice qué.
                Rule::requiredIf(fn (): bool => $cambio instanceof CambioSgsi && $cambio->estado->esComprometido()),
                'nullable',
                'date',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Un cambio sin enunciado es una fila que nadie sabe qué es.',
            'fecha_prevista.required' => 'Un cambio aprobado tiene que decir para cuándo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'cambio',
            'ambito' => 'ámbito',
            'proposito' => 'propósito',
            'responsable_id' => 'responsable',
            'fecha_propuesta' => 'fecha',
            'fecha_prevista' => 'fecha prevista',
        ];
    }
}
