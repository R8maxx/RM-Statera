<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Http\Requests\Concerns\NormalizaSeleccionVacia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los campos de gestión de una implantación.
 *
 * `estado` no está aquí: cambiar de estado pasa por la máquina de estados y por
 * el histórico (`CambiarEstado`), no por un `update` de formulario. Un estado
 * nuevo sin su fila de transición sería un agujero en la traza.
 *
 * Excluir un requisito sin decir por qué es el motivo de rechazo más habitual en
 * una auditoría, así que la justificación es obligatoria en cuanto `aplica` es
 * falso. La base lo respalda con una restricción `CHECK`; esto es para que el
 * mensaje llegue al campo en lugar de como un error de PostgreSQL.
 */
class GuardarImplantacionRequest extends FormRequest
{
    use NormalizaSeleccionVacia;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aplica' => ['required', 'boolean'],
            'justificacion' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('aplica')),
                'nullable',
                'string',
                'max:2000',
            ],
            // Dentro de la organización: el desplegable sólo la ofrece a ella,
            // pero un identificador llega por la petición y no por el desplegable.
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organizacion_id', $this->user()?->organizacion_id),
            ],
            'fecha_objetivo' => ['nullable', 'date'],
            'nivel_madurez' => ['nullable', Rule::enum(NivelMadurez::class)],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'responsable_id' => 'responsable',
            'fecha_objetivo' => 'fecha objetivo',
            'nivel_madurez' => 'nivel de madurez',
            'justificacion' => 'justificación',
        ];
    }

    /**
     * @return list<string>
     */
    protected function seleccionesOpcionales(): array
    {
        return ['responsable_id', 'nivel_madurez'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'justificacion.required' => 'Di por qué este requisito no aplica: es lo primero que un auditor pide cuando ve una exclusión.',
        ];
    }
}
