<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La única fuente de verdad de la validación de un sistema.
 */
class GuardarSistemaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ?Sistema $sistema */
        $sistema = $this->route('sistema');

        return [
            'codigo' => [
                'required',
                'string',
                'max:32',
                // El código es único dentro de la organización, no globalmente:
                // dos clientes distintos pueden llamar SIS-01 a sistemas
                // distintos. La regla lo refleja explícitamente.
                Rule::unique('sistemas', 'codigo')
                    ->where('organizacion_id', $this->user()?->organizacion_id)
                    ->ignore($sistema?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'marco_id' => ['required', 'integer', Rule::exists('marcos', 'id')],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', Rule::enum(EstadoSistema::class)],
            'alcance_declarado' => ['nullable', 'string', 'max:5000'],
            'exclusiones_justificadas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'marco_id' => 'marco',
            'alcance_declarado' => 'alcance declarado',
            'exclusiones_justificadas' => 'exclusiones justificadas',
        ];
    }
}
