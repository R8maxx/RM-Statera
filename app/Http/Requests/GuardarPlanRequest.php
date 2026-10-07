<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un plan (punto 43). Sin precio: el cobro se modela, no se
 * hace.
 */
class GuardarPlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ?Plan $plan */
        $plan = $this->route('plan');

        return [
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('planes', 'codigo')->ignore($plan?->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'limite_cuentas' => ['nullable', 'integer', 'min:1'],
            'limite_sistemas' => ['nullable', 'integer', 'min:1'],
            'dias_gracia' => ['required', 'integer', 'min:0', 'max:365'],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código va en minúsculas, con números y guiones: «basica», «media-10».',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'limite_cuentas' => 'límite de cuentas',
            'limite_sistemas' => 'límite de sistemas',
            'dias_gracia' => 'días de gracia',
        ];
    }

    /**
     * @return array{codigo: string, nombre: string, descripcion: ?string, limite_cuentas: ?int, limite_sistemas: ?int, dias_gracia: int, activo: bool}
     */
    public function datos(): array
    {
        $entero = fn (string $campo): ?int => $this->validated($campo) === null ? null : (int) $this->validated($campo);

        return [
            'codigo' => (string) $this->validated('codigo'),
            'nombre' => (string) $this->validated('nombre'),
            'descripcion' => $this->validated('descripcion'),
            'limite_cuentas' => $entero('limite_cuentas'),
            'limite_sistemas' => $entero('limite_sistemas'),
            'dias_gracia' => (int) $this->validated('dias_gracia'),
            'activo' => $this->boolean('activo'),
        ];
    }
}
