<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Auditoria\Models\Hallazgo;
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
            'incidente_id' => ['nullable', 'integer', 'exists:incidentes,id'],

            'descripcion' => ['required', 'string', 'max:5000'],
            'correccion_inmediata' => ['nullable', 'string', 'max:5000'],
            'analisis_causa_raiz' => ['nullable', 'string', 'max:5000'],

            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_deteccion' => ['required', 'date'],
            'fecha_prevista' => ['nullable', 'date'],
        ];
    }

    /**
     * Reglas que la base también impone, dichas aquí en castellano.
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
                // ofrecen. **Desde el § 4.10 no queda ninguno fuera**, así que
                // esta guarda no la ejerce nadie hoy; se queda porque el día que
                // entre un origen nuevo sin módulo detrás es lo que lo sujeta.
                if (! $origen->disponible()) {
                    $validator->errors()->add('origen', sprintf(
                        'El origen «%s» todavía no se puede usar: su módulo no está implantado.',
                        $origen->etiqueta(),
                    ));

                    return;
                }

                /*
                 * En la edición, la procedencia que ya está guardada fija el
                 * origen: una no conformidad nacida de una prueba de
                 * continuidad o de un incidente no se reclasifica, porque sus
                 * `CHECK` (`no_conformidades_prueba_continuidad_origen_check`,
                 * `incidente_origen_check`) lo rechazarían con un 500 y porque
                 * cambiarlo reescribiría de dónde salió. El formulario ya no
                 * ofrece el desplegable en esos casos; esto es para lo que
                 * llegue sin pasar por él.
                 */
                $enEdicion = $this->route('no_conformidad');

                if ($enEdicion instanceof NoConformidad && $origen !== $enEdicion->origen) {
                    if ($enEdicion->prueba_continuidad_id !== null) {
                        $validator->errors()->add('origen', 'Una no conformidad que viene de una prueba de continuidad conserva ese origen.');

                        return;
                    }

                    if ($enEdicion->incidente_id !== null) {
                        $validator->errors()->add('origen', 'Una no conformidad que viene de un incidente conserva ese origen.');

                        return;
                    }
                }

                if ($this->input('hallazgo_id') !== null && $origen !== OrigenNoConformidad::Auditoria) {
                    $validator->errors()->add('origen', 'Una no conformidad que viene de un hallazgo es de origen auditoría.');
                }

                if ($this->input('incidente_id') !== null && $origen !== OrigenNoConformidad::Incidente) {
                    $validator->errors()->add('origen', 'Una no conformidad que viene de un incidente es de origen incidente.');
                }

                /*
                 * Y las dos a la vez, no. Lo impide además
                 * `no_conformidades_una_procedencia_check`; esto existe para que
                 * el mensaje sea legible y no el nombre de una restricción.
                 */
                if ($this->input('hallazgo_id') !== null && $this->input('incidente_id') !== null) {
                    $validator->errors()->add(
                        'incidente_id',
                        'Una no conformidad sale de un hallazgo o de un incidente, no de los dos: elige de cuál.',
                    );
                }

                /*
                 * Y la puerta que abrió la cláusula 10.1: una oportunidad de
                 * mejora no incumple nada, así que no se trata aquí. El dominio lo
                 * vuelve a comprobar —`RegistrarNoConformidad`—, porque la regla
                 * vale también para un importador; esto es para que el mensaje
                 * llegue al campo en vez de subir como una excepción.
                 */
                $hallazgo = $this->input('hallazgo_id') === null
                    ? null
                    : Hallazgo::query()->find($this->input('hallazgo_id'));

                if ($hallazgo instanceof Hallazgo && ! $hallazgo->tipo->admiteNoConformidad()) {
                    $validator->errors()->add('hallazgo_id', sprintf(
                        'Un hallazgo de tipo «%s» se trata en el registro de oportunidades de mejora, no aquí.',
                        $hallazgo->tipo->etiqueta(),
                    ));
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
            'incidente_id' => 'incidente',
            'responsable_id' => 'responsable',
            'analisis_causa_raiz' => 'análisis de causa raíz',
            'correccion_inmediata' => 'corrección inmediata',
        ];
    }
}
