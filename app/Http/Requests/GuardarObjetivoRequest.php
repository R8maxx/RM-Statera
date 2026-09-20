<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un objetivo de seguridad. Cláusula 6.2.
 *
 * La única fuente de verdad de la validación, como en el resto del proyecto: el
 * controlador ni comprueba ni reinterpreta lo que llega aquí.
 *
 * **El estado no está entre las reglas, y es deliberado.** Se mueve por su ruta de
 * transición, que es la que escribe la firma, la fecha de cierre y el histórico;
 * admitirlo aquí dejaría aprobar un objetivo desde el formulario de edición sin
 * fila en `objetivo_transiciones` y sin firmante, y la base rechazaría lo segundo
 * con un error que no menciona la palabra «firma». Mismo reparto que en tareas,
 * en auditorías y en no conformidades.
 *
 * **La fecha objetivo se pide aquí y no se exige, y la exige la transición.** Un
 * objetivo en borrador se escribe como se pueda; uno aprobado tiene que decir
 * para cuándo, porque la 6.2 lo pide por escrito. Obligarla en el formulario
 * impediría apuntar la idea el día que se tiene, que es cuando la gente la
 * apunta; no exigirla nunca dejaría pasar un compromiso sin plazo. Por eso está
 * en los dos sitios que corresponden: opcional al escribir, obligatoria al firmar.
 */
class GuardarObjetivoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $objetivo = $this->route('objetivo');
        $id = $objetivo instanceof Objetivo ? $objetivo->id : null;

        return [
            'codigo' => [
                'required', 'string', 'max:60',
                // Único dentro de la organización, no del mundo: dos clientes
                // pueden llamar igual a su primer objetivo del año.
                Rule::unique('objetivos_seguridad', 'codigo')
                    ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                    ->ignore($id),
            ],

            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],

            // Qué recursos (6.2, planificación b). Texto libre y no una cifra:
            // ver la cabecera de la migración.
            'recursos' => ['nullable', 'string', 'max:5000'],

            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio()),
            ],

            // Sin `after_or_equal:today`, como en tareas: un objetivo que se
            // apunta tarde lleva la fecha que le tocaba, y falsearla para que el
            // formulario la acepte es peor que verlo vencido desde el primer día.
            'fecha_objetivo' => ['nullable', 'date'],
        ];
    }

    /**
     * La regla que la base también impone, dicha aquí en castellano.
     *
     * Sin ella, quitarle la fecha a un objetivo ya aprobado sube como el error de
     * `objetivos_seguridad_plazo_check`, que no menciona ni el plazo ni el
     * compromiso. La transición lo comprueba por su lado, porque la regla vale
     * también para un importador.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $objetivo = $this->route('objetivo');

                if (! $objetivo instanceof Objetivo || ! $objetivo->estado->esComprometido()) {
                    return;
                }

                if ($this->input('fecha_objetivo') === null || $this->input('fecha_objetivo') === '') {
                    $validator->errors()->add(
                        'fecha_objetivo',
                        'Un objetivo aprobado tiene que decir para cuándo: la cláusula 6.2 lo pide por escrito.',
                    );
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
            'titulo.required' => 'Un objetivo sin enunciado es una fila que nadie sabe qué es.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'objetivo',
            'responsable_id' => 'responsable',
            'fecha_objetivo' => 'fecha objetivo',
        ];
    }
}
