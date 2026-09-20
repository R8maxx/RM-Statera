<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una revisión por la dirección. Cláusula 9.3.
 *
 * **El estado no está entre las reglas**, como en el resto del producto: se mueve
 * por su ruta, y aprobar ni siquiera pasa por ahí —lo hace `AprobarRevision`,
 * porque no es un cambio de estado sino el acto que congela las siete entradas—.
 *
 * `periodo_hasta` se valida contra `periodo_desde` aquí además de en el `CHECK`
 * de la base: un periodo al revés es el dedazo más fácil de dar en un formulario
 * con dos fechas, y el error de la restricción habla de
 * `revisiones_direccion_periodo_check` y no de lo que la persona estaba
 * rellenando.
 */
class GuardarRevisionDireccionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $revision = $this->route('revision_direccion');
        $id = $revision instanceof RevisionDireccion ? $revision->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('revisiones_direccion', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            // Sin `after_or_equal:today`: una revisión celebrada en enero se
            // registra en marzo, y es el caso normal y no la excepción.
            'fecha' => ['required', 'date'],

            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],

            'asistentes' => ['nullable', 'string', 'max:5000'],
            'conclusiones' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo_hasta.after_or_equal' => 'El periodo revisado no puede terminar antes de empezar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha de celebración',
            'periodo_desde' => 'inicio del periodo',
            'periodo_hasta' => 'fin del periodo',
        ];
    }
}
