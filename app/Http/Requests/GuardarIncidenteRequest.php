<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un incidente. § 4.10.
 *
 * **Lo que se pide es lo mínimo para que el registro exista**, y es deliberado:
 * quien apunta un incidente a las tres de la mañana no tiene todavía ni la
 * clasificación ni el impacto, y un formulario que se los exija hace que el
 * incidente se apunte en otro sitio —o no se apunte—. Lo que sí se exige, y en el
 * dominio además del `CHECK`, es la lección aprendida **al cerrar**.
 *
 * **`notificado_*_en` no se toca aquí**: cada notificación tiene su ruta, porque
 * anotar que se notificó a un supervisor es un acto con fecha y no un campo de un
 * formulario largo que alguien rellena de pasada.
 */
class GuardarIncidenteRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $incidente = $this->route('incidente');
        $id = $incidente instanceof Incidente ? $incidente->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('incidentes', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:10000'],

            // Opcional: un correo fraudulento a toda la organización no es de
            // ningún sistema, y obligarlo haría elegir el que menos mal suene.
            'sistema_id' => ['nullable', 'integer', Rule::exists('sistemas', 'id')],

            'clasificacion' => ['required', Rule::enum(ClasificacionIncidente::class)],
            'peligrosidad' => ['required', Rule::enum(PeligrosidadIncidente::class)],

            'fecha_deteccion' => ['required', 'date'],
            'fecha_inicio' => ['nullable', 'date', 'before_or_equal:fecha_deteccion'],

            'afecta_confidencialidad' => ['boolean'],
            'afecta_integridad' => ['boolean'],
            'afecta_disponibilidad' => ['boolean'],
            'afecta_autenticidad' => ['boolean'],
            'afecta_trazabilidad' => ['boolean'],

            'impacto' => ['nullable', 'string', 'max:10000'],
            'acciones_contencion' => ['nullable', 'string', 'max:10000'],
            'leccion_aprendida' => ['nullable', 'string', 'max:10000'],

            'responsable_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],

            /*
             * **Notificable sí, notificado no.** Marcar que hay datos personales
             * de por medio es una decisión que se toma al registrar; anotar la
             * notificación es un acto posterior y tiene su ruta.
             */
            'notificable_aepd' => ['boolean'],
            'notificable_ccn_cert' => ['boolean'],

            'activos' => ['sometimes', 'array'],
            'activos.*' => ['integer', Rule::exists('activos', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.before_or_equal' => 'No se detecta lo que todavía no ha empezado: revisa las dos fechas.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_deteccion' => 'fecha de detección',
            'fecha_inicio' => 'fecha de inicio',
            'notificable_aepd' => 'notificable a la AEPD',
            'notificable_ccn_cert' => 'notificable al CCN-CERT',
        ];
    }

    protected function seleccionesOpcionales(): array
    {
        return ['sistema_id', 'responsable_id'];
    }

    /**
     * @return list<string>
     */
    protected function gruposDeCasillas(): array
    {
        return ['activos', 'dimensiones', 'notificables'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarCentinelas();

        /*
         * Una casilla sin marcar no viaja en el formulario, así que sin esto
         * desmarcarla dejaría el valor anterior puesto: el incidente seguiría
         * contando como notificable a la AEPD y el reloj seguiría corriendo en
         * rojo sin que nadie entendiera por qué.
         *
         * **Y se derivan de un array sólo si el array viene**, nunca a ciegas:
         * la pantalla manda `dimensiones[]` y `notificables[]` porque así puede
         * usar `CampoCasillas` —con su `fieldset`, su `legend` y sus errores—,
         * pero un importador o un test que postee los booleanos sueltos tiene
         * que seguir funcionando. Derivar siempre apagaría las cinco dimensiones
         * en cualquier petición que no venga de este formulario.
         */
        $this->merge([
            ...$this->booleanosDe('dimensiones', array_keys(Incidente::DIMENSIONES)),
            ...$this->booleanosDe('notificables', ['notificable_aepd', 'notificable_ccn_cert']),
        ]);
    }

    /**
     * Los booleanos de un grupo: desde su array si vino, y si no uno a uno.
     *
     * @param  list<string>  $columnas
     * @return array<string, bool>
     */
    private function booleanosDe(string $grupo, array $columnas): array
    {
        $marcadas = $this->has($grupo) ? (array) $this->input($grupo, []) : null;

        $valores = [];

        foreach ($columnas as $columna) {
            $valores[$columna] = $marcadas === null
                ? $this->boolean($columna)
                : in_array($columna, $marcadas, true);
        }

        return $valores;
    }
}
