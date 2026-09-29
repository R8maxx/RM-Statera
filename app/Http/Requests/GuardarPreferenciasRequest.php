<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Usuario\Enums\PaginaInicio;
use App\Domain\Usuario\Enums\Tema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las preferencias de la cuenta propia.
 *
 * Todas con `sometimes`: el menú de la cuenta manda sólo el tema al pulsarlo, y
 * «Mi cuenta» manda el bloque que se guarda. Una preferencia que no llega no
 * se toca, en vez de volver a su valor por defecto.
 */
class GuardarPreferenciasRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tema' => ['sometimes', Rule::enum(Tema::class)],
            'pagina_inicio' => ['sometimes', Rule::enum(PaginaInicio::class)],
            'avisos_por_correo' => ['sometimes', 'boolean'],
        ];
    }
}
