<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una no conformidad.
 *
 * La única fuente de verdad de la validación, como en el resto del proyecto: el
 * controlador ni comprueba ni reinterpreta lo que llega aquí.
 *
 * **El estado no está entre las reglas, y es deliberado.** Se mueve por su ruta
 * de transición, que es la que escribe las fechas y el histórico; admitirlo aquí
 * dejaría cerrar una no conformidad desde el formulario de edición sin fila en
 * `no_conformidad_transiciones` y sin `fecha_cierre`, y la base rechazaría lo
 * segundo con un error que no menciona la palabra «cierre». Mismo reparto que en
 * tareas y en auditorías.
 */
class GuardarNoConformidadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $noConformidad = $this->route('no_conformidad');
        $id = $noConformidad instanceof NoConformidad ? $noConformidad->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                // Único dentro de la organización, no del mundo: dos clientes
                // pueden llamar igual a su primera no conformidad del año.
                Rule::unique('no_conformidades', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'origen' => ['required', Rule::enum(OrigenNoConformidad::class)],

            /*
             * El hallazgo sólo entra en el alta. En la edición no se toca: mover
             * una no conformidad de un hallazgo a otro reescribiría de qué
             * auditoría salió, que es justo lo que el registro tiene que fijar.
             */
            'hallazgo_id' => ['nullable', 'integer', 'exists:hallazgos,id'],

            'descripcion' => ['required', 'string', 'max:5000'],
            'correccion_inmediata' => ['nullable', 'string', 'max:5000'],
            'analisis_causa_raiz' => ['nullable', 'string', 'max:5000'],

            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_deteccion' => ['required', 'date'],
            'fecha_prevista' => ['nullable', 'date'],
        ];
    }

    /**
     * Dos reglas que la base también impone, dichas aquí en castellano.
     *
     * Sin ellas el error que sube es el de la restricción —habla de
     * `no_conformidades_hallazgo_origen_check`— y no de lo que la persona estaba
     * rellenando.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $origen = OrigenNoConformidad::tryFrom((string) $this->input('origen'));

                if ($origen === null) {
                    return;
                }

                // Los orígenes cuyo módulo no existe se declaran y no se
                // ofrecen: una no conformidad marcada «de un incidente» sin
                // incidente detrás no es trazable, es una etiqueta.
                if (! $origen->disponible()) {
                    $validator->errors()->add('origen', sprintf(
                        'El origen «%s» todavía no se puede usar: su módulo no está implantado.',
                        $origen->etiqueta(),
                    ));

                    return;
                }

                if ($this->input('hallazgo_id') !== null && $origen !== OrigenNoConformidad::Auditoria) {
                    $validator->errors()->add('origen', 'Una no conformidad que viene de un hallazgo es de origen auditoría.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hallazgo_id' => 'hallazgo',
            'responsable_id' => 'responsable',
            'analisis_causa_raiz' => 'análisis de causa raíz',
            'correccion_inmediata' => 'corrección inmediata',
        ];
    }
}
