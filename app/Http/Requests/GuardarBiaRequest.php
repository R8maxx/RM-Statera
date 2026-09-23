<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición del BIA de un servicio. § 4.11.
 *
 * **`activo_id` sólo se valida en el alta.** Un BIA no cambia de servicio: es
 * `EditarBia` quien decide qué campos admite una edición, y `activo_id` no está
 * entre ellos —cambiar de servicio no es editar un BIA, es registrar uno
 * distinto—. Con la ruta llevando `{bia}` sólo en `update`, basta con mirar si
 * el `FormRequest` está resolviendo sobre uno para saber en qué caso está.
 *
 * **Sin `authorize()`**: la autorización va en la ruta (`can:continuidad.*`) y
 * en el controlador para el caso de `continuidad.aprobar`, nunca aquí.
 */
class GuardarBiaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [
            'impacto_4h' => ['required', Rule::enum(NivelImpacto::class)],
            'impacto_1d' => ['required', Rule::enum(NivelImpacto::class)],
            'impacto_3d' => ['required', Rule::enum(NivelImpacto::class)],
            'impacto_1s' => ['required', Rule::enum(NivelImpacto::class)],
            'impacto_1m' => ['required', Rule::enum(NivelImpacto::class)],

            'rto_horas' => ['required', 'integer', 'min:1'],
            'rpo_horas' => ['required', 'integer', 'min:0'],

            'justificacion' => ['nullable', 'string', 'max:10000'],

            'responsable_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],
        ];

        if (! $this->route('bia') instanceof BiaServicio) {
            $reglas['activo_id'] = [
                'required', 'integer',
                Rule::exists('activos', 'id')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->where('tipo', TipoActivo::Servicios->value),
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
            'activo_id' => 'servicio',
            'impacto_4h' => 'impacto a las 4 horas',
            'impacto_1d' => 'impacto a 1 día',
            'impacto_3d' => 'impacto a 3 días',
            'impacto_1s' => 'impacto a 1 semana',
            'impacto_1m' => 'impacto a 1 mes',
            'rto_horas' => 'RTO',
            'rpo_horas' => 'RPO',
        ];
    }

    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }
}
