<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Enums\TramoImpacto;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición del BIA de un servicio. § 4.11.
 *
 * **`activo_id` sólo se valida en el alta.** Un BIA no cambia de servicio: es
 * `EditarBia` quien decide qué campos admite una edición, y `activo_id` no está
 * entre ellos —cambiar de servicio no es editar un BIA, es registrar uno
 * distinto—. Con la ruta llevando `{bia}` sólo en `update`, basta con mirar si
 * el `FormRequest` está resolviendo sobre uno para saber en qué caso está.
 *
 * **Dos reglas que la base también impone, dichas aquí en castellano**: que el
 * impacto no baje con el tiempo (`bia_servicios_monotonia_check`) y que el
 * servicio no tenga ya su BIA (el único `(organizacion_id, activo_id)`). Sin
 * ellas, un formulario rellenado de forma perfectamente predecible subía como
 * un 500 con el nombre de una restricción.
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
                // Un servicio, un BIA: el índice único lo impone igual, pero
                // su error es un `QueryException` y no un mensaje en el campo.
                Rule::unique('bia_servicios', 'activo_id')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ];
        }

        return $reglas;
    }

    /**
     * La monotonía de los cinco tramos, antes de que la rechace el `CHECK`.
     *
     * **En la edición se compara lo que llega sobre lo que ya hay**: un tramo
     * que no viaja conserva el valor guardado, y es contra ése contra el que
     * tiene que medirse el vecino que sí cambia. El orden sale de
     * `TramoImpacto::cases()` y el peso de `NivelImpacto::peso()`, los mismos
     * que recorre el `CHECK` escrito a mano; aquí no se repite ninguno de los dos.
     *
     * El error cae en el primer tramo que baja, que es el que hay que tocar.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $bia = $this->route('bia');
                $anterior = null;

                foreach (TramoImpacto::cases() as $tramo) {
                    $campo = $tramo->value;

                    if ($validator->errors()->has($campo)) {
                        return;
                    }

                    $nivel = $this->has($campo)
                        ? NivelImpacto::tryFrom((string) $this->input($campo))
                        : ($bia instanceof BiaServicio ? $bia->{$campo} : null);

                    if (! $nivel instanceof NivelImpacto) {
                        return;
                    }

                    if ($anterior !== null && $nivel->peso() < $anterior[1]->peso()) {
                        $validator->errors()->add($campo, sprintf(
                            'El impacto no puede bajar con el tiempo: a %s es «%s», menos que «%s» a %s. '
                            .'Un servicio caído más tiempo hace, como poco, el mismo daño.',
                            $tramo->etiqueta(),
                            $nivel->etiqueta(),
                            $anterior[1]->etiqueta(),
                            $anterior[0]->etiqueta(),
                        ));

                        return;
                    }

                    $anterior = [$tramo, $nivel];
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
            'activo_id' => 'servicio',
            'impacto_4h' => 'impacto a las 4 horas',
            'impacto_1d' => 'impacto a 1 día',
            'impacto_3d' => 'impacto a 3 días',
            'impacto_1s' => 'impacto a 1 semana',
            'impacto_1m' => 'impacto a 1 mes',
            'rto_horas' => 'RTO',
            'rpo_horas' => 'RPO',
            'responsable_id' => 'responsable',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activo_id.unique' => 'Ese servicio ya tiene su BIA: un servicio, un análisis. Edita el que hay en vez de registrar otro.',
        ];
    }

    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id'];
    }
}
