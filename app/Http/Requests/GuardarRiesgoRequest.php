<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La única fuente de verdad de la validación de un riesgo.
 *
 * **La valoración no entra aquí.** Se registra por su propia ruta, que es la que
 * jubila la anterior y congela la escala; dejarla entrar por el formulario
 * permitiría cambiar un número sin dejar rastro de con qué se midió, que es lo que
 * el histórico existe para impedir.
 *
 * `codigo` tampoco: lo asigna `CrearRiesgo`, porque el hueco entre números no se
 * rellena —un salto no confunde a nadie y un código repetido en dos informes ya
 * entregados es un hallazgo—.
 */
class GuardarRiesgoRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],

            /*
             * Una del catálogo o una escrita a mano, nunca las dos. Lo comprueba
             * además un `CHECK` en la base: esto es para dar un mensaje legible,
             * no para ser la única defensa.
             */
            'amenaza_id' => ['nullable', 'integer', Rule::exists('amenazas', 'id')->where('vigente', true)],
            'amenaza_libre' => ['nullable', 'string', 'max:255'],

            'vulnerabilidad' => ['nullable', 'string', 'max:5000'],
            'propietario_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],

            // Sin `after_or_equal:today`, como en las tareas: un riesgo cuya
            // reevaluación ya tocaba lleva la fecha que le tocaba, y falsearla para
            // que el formulario la acepte es peor que verla vencida desde el
            // primer día.
            'fecha_revision' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:5000'],

            // Sobre qué pesa. El dominio exige al menos uno; aquí se comprueba
            // para dar el mensaje antes de llegar a la excepción.
            'activos' => ['required', 'array', 'min:1'],
            'activos.*' => ['integer', Rule::exists('activos', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $delCatalogo = $this->filled('amenaza_id');
            $aMano = $this->filled('amenaza_libre');

            if ($delCatalogo === $aMano) {
                $validator->errors()->add('amenaza_id', $delCatalogo
                    ? 'Elige una amenaza del catálogo o escríbela a mano, pero no las dos.'
                    : 'Un riesgo necesita una amenaza: la que lo produce.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'Un riesgo sin título es una fila que nadie sabe qué es.',
            'activos.required' => 'Un riesgo pesa sobre al menos un activo: el impacto se deduce de lo que valen.',
            'activos.min' => 'Un riesgo pesa sobre al menos un activo: el impacto se deduce de lo que valen.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amenaza_id' => 'amenaza',
            'amenaza_libre' => 'amenaza',
            'propietario_id' => 'propietario del riesgo',
            'fecha_revision' => 'fecha de reevaluación',
        ];
    }

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['propietario_id', 'amenaza_id'];
    }

    /**
     * Los activos llegan de un grupo de casillas.
     *
     * Aquí el centinela hacía más daño que en ningún otro sitio: `[null]` cuenta
     * como un elemento, así que `min:1` lo daba por bueno y el mensaje escrito
     * para este caso —«Un riesgo pesa sobre al menos un activo»— **no podía
     * dispararse nunca**. Fallaba en `activos.0`, con el mensaje genérico.
     *
     * @return list<string>
     */
    protected function gruposDeCasillas(): array
    {
        return ['activos'];
    }
}
