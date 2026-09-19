<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un indicador.
 *
 * La única fuente de verdad de la validación. Las mediciones no entran aquí: se
 * sellan por su propia ruta, que es la que congela el objetivo y la fecha.
 * Admitirlas en este formulario dejaría cambiar una cifra ya sellada al editar
 * el nombre del indicador.
 */
class GuardarIndicadorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $indicador = $this->route('indicador');
        $id = $indicador instanceof Indicador ? $indicador->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                // Único dentro de la organización, no del mundo: dos clientes
                // pueden llamar igual a su primer indicador.
                Rule::unique('indicadores', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:2000'],

            'origen' => ['required', Rule::enum(OrigenMedicion::class)],
            'calculo' => ['nullable', Rule::enum(CalculoIndicador::class)],
            'formula_o_fuente' => ['nullable', 'string', 'max:2000'],

            'marco_id' => ['nullable', 'integer', 'exists:marcos,id'],

            'unidad' => ['required', Rule::enum(UnidadIndicador::class)],
            'sentido' => ['required', Rule::enum(SentidoIndicador::class)],
            'periodicidad' => ['required', Rule::enum(Periodicidad::class)],

            'objetivo' => ['nullable', 'numeric', 'between:-9999999999,9999999999'],

            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * Las tres reglas que la base también impone, dichas aquí en castellano.
     *
     * Sin ellas el error que sube es el de la restricción —habla de
     * `indicadores_calculado_check`— y no de lo que la persona estaba haciendo.
     * Mismo reparto que en el resto del proyecto: el `CHECK` es la garantía y el
     * `FormRequest` es la explicación.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                $origen = $this->input('origen');

                if ($origen === OrigenMedicion::Calculado->value && $this->input('calculo') === null) {
                    $validador->errors()->add(
                        'calculo',
                        'Elige qué calcula Statera: un indicador calculado sin cálculo no se puede medir.',
                    );
                }

                if ($origen === OrigenMedicion::Manual->value && trim((string) $this->input('formula_o_fuente')) === '') {
                    $validador->errors()->add(
                        'formula_o_fuente',
                        'Escribe de dónde sale la cifra. Es lo que pregunta la cláusula 9.1 b), y «lo cuenta el responsable del servicio desde la hoja de registro» es una respuesta válida.',
                    );
                }

                /*
                 * Un marco acota un cálculo sobre implantaciones. Sobre los demás
                 * no significa nada: las evidencias, las tareas y los activos son
                 * de la organización entera y sirven a los dos marcos a la vez
                 * (invariante 6). Dejarlo puesto haría creer que la cifra sale
                 * sólo de ese marco.
                 */
                $calculo = $this->input('calculo');

                if ($this->input('marco_id') !== null && $calculo !== null) {
                    $caso = CalculoIndicador::tryFrom((string) $calculo);

                    if ($caso !== null && ! $caso->admiteMarco()) {
                        $validador->errors()->add(
                            'marco_id',
                            'Ese cálculo no se puede acotar a un marco: cuenta sobre datos que sirven a los dos a la vez.',
                        );
                    }
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
            'codigo.unique' => 'Ya hay un indicador con ese código.',
            'nombre.required' => 'Escribe qué mide el indicador.',
        ];
    }
}
