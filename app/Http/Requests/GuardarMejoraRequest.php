<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una oportunidad de mejora. Cláusula 10.1.
 *
 * **El estado no está entre las reglas**, como en el resto de registros con
 * máquina de estados: se mueve por su ruta de transición, que es la que escribe la
 * fecha de cierre y el histórico.
 *
 * **Y la fecha prevista no se exige nunca**, a diferencia de la fecha objetivo de
 * un objetivo aprobado: aquí nadie se compromete. Pedirla convertiría el registro
 * en un trámite y lo que hace la gente entonces es no apuntar la idea.
 */
class GuardarMejoraRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mejora = $this->route('mejora');
        $id = $mejora instanceof Mejora ? $mejora->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('mejoras', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'origen' => ['required', Rule::enum(OrigenMejora::class)],

            // Sólo en el alta: mover una mejora de un hallazgo a otro reescribiría
            // de qué auditoría salió, que es lo que el registro tiene que fijar.
            'hallazgo_id' => ['nullable', 'integer', 'exists:hallazgos,id'],

            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'beneficio_esperado' => ['nullable', 'string', 'max:5000'],

            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],
            'fecha_deteccion' => ['required', 'date'],
            'fecha_prevista' => ['nullable', 'date'],
        ];
    }

    /**
     * Las dos reglas que la base también impone, dichas aquí en castellano.
     *
     * Sin ellas el error que sube es el de la restricción —habla de
     * `mejoras_hallazgo_origen_check`— y no de lo que la persona estaba
     * rellenando.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $origen = OrigenMejora::tryFrom((string) $this->input('origen'));

                if ($origen === null) {
                    return;
                }

                if ($this->input('hallazgo_id') !== null && $origen !== OrigenMejora::Auditoria) {
                    $validator->errors()->add('origen', 'Una mejora que viene de un hallazgo es de origen auditoría.');
                }

                /*
                 * El espejo de la puerta que hay en el registro de al lado: una no
                 * conformidad no se trata aquí. Los dos tipos tienen registro
                 * propio desde la 10.1, y mezclarlos haría que una cifra dejara de
                 * significar lo que dice.
                 */
                $hallazgo = $this->input('hallazgo_id') === null
                    ? null
                    : Hallazgo::query()->find($this->input('hallazgo_id'));

                if ($hallazgo instanceof Hallazgo && ! $hallazgo->tipo->abreMejora()) {
                    $validator->errors()->add('hallazgo_id', sprintf(
                        'Un hallazgo de tipo «%s» se trata como no conformidad (cláusula 10.2), no aquí.',
                        $hallazgo->tipo->etiqueta(),
                    ));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Una mejora sin enunciado es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'mejora',
            'hallazgo_id' => 'hallazgo',
            'responsable_id' => 'responsable',
            'beneficio_esperado' => 'beneficio esperado',
        ];
    }
}
