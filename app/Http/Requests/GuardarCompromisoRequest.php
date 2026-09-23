<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Obligacion\Cadencia;
use App\Domain\Obligacion\CodigoCompromiso;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El alta y la edición de un compromiso periódico.
 *
 * **`obligacion_id` no llega por aquí.** Asumir una obligación del catálogo tiene
 * su propia ruta y su propia acción, que copia el título y la cadencia sugerida;
 * dejar que el formulario apuntara a una obligación permitiría reetiquetar un
 * compromiso como si saliera de otra cosa, y `obligacion_id` es justamente lo que
 * el importador mira para avisar de a cuántos afecta retirar una fila del
 * catálogo.
 *
 * El código sí llega, y es opcional: `CodigoCompromiso` propone, no impone — una
 * organización que ya llevaba esto en una hoja llega con su propia numeración.
 */
class GuardarCompromisoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * Los dos desplegables que se pueden dejar en blanco.
     *
     * Reka prohíbe el `SelectItem` con valor vacío, así que la interfaz manda un
     * centinela y aquí se traduce a nulo. Sin esto **no se podía desasignar**:
     * elegido un responsable, no había forma de volver a «sin asignar», pese a que
     * la columna es nullable y la ayuda del campo dice que se puede dejar en blanco.
     *
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['sistema_id', 'responsable_id'];
    }

    /**
     * El código vacío se rellena aquí, y no en el controlador.
     *
     * `codigo` es `nullable` porque el alta admite no traerlo —lo propone
     * `CodigoCompromiso`—, pero la columna es `NOT NULL` con
     * `CHECK (length(trim(codigo)) > 0)`. `RegistrarCompromiso` cubría el nulo y
     * `update()` no, así que **vaciar el campo al editar daba un `QueryException`**
     * en vez de un error que se entienda.
     *
     * Ponerlo aquí hace que el alta y la edición compartan la misma regla, que es
     * lo que un `FormRequest` existe para garantizar.
     */
    protected function prepareForValidation(): void
    {
        // El trait lo separa de su propio gancho justamente para esto.
        $this->normalizarCentinelas();

        if (trim((string) $this->input('codigo')) === '') {
            $this->merge(['codigo' => app(CodigoCompromiso::class)->siguiente()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $compromiso = $this->route('compromiso');

        return [
            'codigo' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('compromisos', 'codigo')
                    ->where('organizacion_id', $this->user()?->organizacion_id)
                    ->ignore($compromiso),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'periodicidad_meses' => ['required', 'integer', 'between:'.Cadencia::MINIMO_MESES.','.Cadencia::MAXIMO_MESES],
            /*
             * Desde cuándo corre el reloj. Se admite en el pasado —«la última
             * auditoría fue en marzo»— y **no en el futuro**: comprometerse a algo
             * a partir del año que viene es no estar comprometido todavía, y el
             * calendario pintaría un chip que no significa nada.
             */
            'computa_desde' => ['required', 'date', 'before_or_equal:today'],
            'sistema_id' => ['nullable', 'integer', 'exists:sistemas,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'computa_desde.before_or_equal' => 'La fecha desde la que se cuenta no puede estar en el futuro: hasta que llegue, no hay compromiso que vencer.',
            'periodicidad_meses.between' => 'La cadencia va de un mes a diez años. Por encima de eso lo que hay es un error de tecleo.',
        ];
    }
}
