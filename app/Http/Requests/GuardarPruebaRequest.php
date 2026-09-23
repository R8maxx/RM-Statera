<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Planifica y edita una prueba de continuidad. § 4.11 y `op.cont.3`.
 *
 * **`documento_id` sólo se valida en el alta**, por el mismo motivo que
 * `activo_id` en `GuardarBiaRequest`: una prueba no cambia de plan, es
 * `EditarPrueba` quien decide qué campos admite una edición y `documento_id`
 * no está entre ellos. Con la ruta llevando `{prueba}` sólo en `update`, basta
 * con mirar si el `FormRequest` está resolviendo sobre una para saber en qué
 * caso está.
 *
 * **`codigo` es editable y único por organización**, como en
 * `GuardarIncidenteRequest`: se propone con `CodigoPrueba::siguiente()` y
 * quien planifica puede escribir encima.
 *
 * **Sin `authorize()`**: la autorización va en la ruta (`can:continuidad.*`),
 * nunca aquí.
 */
class GuardarPruebaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $prueba = $this->route('prueba');
        $id = $prueba instanceof PruebaContinuidad ? $prueba->id : null;
        $organizacionId = app(ContextoOrganizacion::class)->idObligatorio();

        $reglas = [
            'codigo' => [
                'required', 'string', 'max:60',
                Rule::unique('pruebas_continuidad', 'codigo')
                    ->where('organizacion_id', $organizacionId)
                    ->ignore($id),
            ],

            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoPrueba::class)],
            'fecha_prevista' => ['required', 'date'],

            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => [
                'integer',
                Rule::exists('activos', 'id')
                    ->where('organizacion_id', $organizacionId)
                    ->where('tipo', TipoActivo::Servicios->value),
            ],

            'responsable_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $organizacionId),
            ],
        ];

        if (! $prueba instanceof PruebaContinuidad) {
            $reglas['documento_id'] = [
                'required', 'integer',
                Rule::exists('documentos', 'id')
                    ->where('organizacion_id', $organizacionId)
                    ->where('tipo', TipoDocumento::PlanContinuidad->value),
            ];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'documento_id' => 'plan de continuidad',
            'fecha_prevista' => 'fecha prevista',
            'responsable_id' => 'responsable',
        ];
    }

    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }

    /**
     * @return list<string>
     */
    protected function gruposDeCasillas(): array
    {
        return ['servicios'];
    }
}
