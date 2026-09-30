<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adjunta la evidencia a una prueba de continuidad realizada.
 *
 * **Obligatoria, a diferencia de `RegistrarResultadoPruebaRequest`**: aquí no
 * hay nada más que enviar, y quitar la evidencia de una prueba ya realizada no
 * es algo que esta puerta haga. Acotada a la organización a mano, como toda
 * regla `exists` (`ConsultasDeUsuarioAcotadasTest`).
 */
class AdjuntarEvidenciaPruebaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evidencia_id' => [
                'required', 'integer',
                Rule::exists('evidencias', 'id')->where(
                    'organizacion_id',
                    app(ContextoOrganizacion::class)->idObligatorio(),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['evidencia_id' => 'evidencia'];
    }
}
