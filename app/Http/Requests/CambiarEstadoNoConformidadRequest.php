<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de una no conformidad, desde su ficha.
 *
 * La nota es obligatoria en tres transiciones y opcional en el resto. El dominio
 * lo vuelve a comprobar —`CambiarEstadoNoConformidad`—, porque la regla vale
 * también para un importador; esto es para que el mensaje llegue al campo en vez
 * de subir como una excepción.
 *
 * - **anular**, que es decidir que aquello no era una no conformidad;
 * - **verificar**, donde la nota *es* el resultado de la verificación de eficacia
 *   —qué se comprobó— y acaba en su columna;
 * - **reabrir el tratamiento** desde algo ya cerrado, que es la verificación que
 *   salió mal y donde «qué falló» es lo único que explica el ir y venir.
 */
class CambiarEstadoNoConformidadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoNoConformidad::class)],
            'nota' => [
                Rule::requiredIf(fn (): bool => $this->exigeMotivo()),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'Esta transición exige decir por qué: sin motivo escrito, no se puede defender delante de un auditor.',
        ];
    }

    private function exigeMotivo(): bool
    {
        $destino = EstadoNoConformidad::tryFrom((string) $this->input('estado'));
        $actual = $this->route('no_conformidad');

        if ($destino === EstadoNoConformidad::Anulada || $destino === EstadoNoConformidad::Verificada) {
            return true;
        }

        return $destino === EstadoNoConformidad::EnTratamiento
            && $actual instanceof NoConformidad
            && $actual->estado->esCerrada();
    }
}
