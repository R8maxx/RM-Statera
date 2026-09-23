<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sella el resultado de una prueba de continuidad. § 4.11 y `op.cont.3`.
 *
 * **`resultado` valida con `Rule::enum`, no a mano.** `ResultadoPrueba::from()`
 * lanza `ValueError` sobre un valor que no reconoce, y eso es un 500 y no un
 * error de formulario si nada lo ataja antes.
 *
 * **`servicios` no valida las claves contra lo que la prueba cubre.** Ese
 * cruce —que cada `activo_id` sea uno de los servicios de esta prueba y no
 * uno cualquiera— lo hace `RegistrarResultadoPrueba` en el dominio, con
 * `TransicionDePruebaNoPermitida::servicioAjeno()`: la regla vale igual para
 * lo que llegue por un importador el día que exista uno.
 */
class RegistrarResultadoPruebaRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_realizacion' => ['required', 'date', 'before_or_equal:today'],
            'resultado' => ['required', Rule::enum(ResultadoPrueba::class)],
            'conclusiones' => ['nullable', 'string', 'max:10000'],

            'evidencia_id' => [
                'nullable', 'integer',
                Rule::exists('evidencias', 'id')->where(
                    'organizacion_id',
                    app(ContextoOrganizacion::class)->idObligatorio(),
                ),
            ],

            'servicios' => ['nullable', 'array'],
            'servicios.*.rto_alcanzado_horas' => ['nullable', 'integer', 'min:0'],
            'servicios.*.rpo_alcanzado_horas' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_realizacion' => 'fecha de realización',
            'evidencia_id' => 'evidencia',
        ];
    }

    protected function seleccionesOpcionales(): array
    {
        return ['evidencia_id'];
    }
}
