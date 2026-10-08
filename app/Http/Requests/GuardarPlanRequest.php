<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Plataforma\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un plan (punto 43). El precio se modela y no se cobra
 * (punto 51): se escribe en euros y se guarda en céntimos, sin IVA.
 */
class GuardarPlanRequest extends FormRequest
{
    /** «49,90» es como se escribe un precio en España; el validador espera el punto. */
    protected function prepareForValidation(): void
    {
        $precio = $this->input('precio_mensual');

        if (is_string($precio)) {
            $precio = trim(str_replace(',', '.', $precio));
            $this->merge(['precio_mensual' => $precio === '' ? null : $precio]);
        }
    }

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
            'precio_mensual' => ['nullable', 'required_if_accepted:contratable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'descuento_anual' => ['nullable', 'integer', 'min:0', 'max:90'],
            'contratable' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código va en minúsculas, con números y guiones: «basica», «media-10».',
            'precio_mensual.required_if_accepted' => 'Un plan que la organización contrata por su cuenta necesita precio.',
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
            'precio_mensual' => 'precio al mes',
            'descuento_anual' => 'descuento anual',
        ];
    }

    /**
     * @return array{codigo: string, nombre: string, descripcion: ?string, limite_cuentas: ?int, limite_sistemas: ?int, dias_gracia: int, activo: bool, precio_mensual_centimos: ?int, descuento_anual: int, contratable: bool}
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
            // En céntimos y redondeado: un float no es un importe.
            'precio_mensual_centimos' => $this->validated('precio_mensual') === null ? null : (int) round((float) $this->validated('precio_mensual') * 100),
            'descuento_anual' => (int) ($this->validated('descuento_anual') ?? 0),
            'contratable' => $this->boolean('contratable'),
        ];
    }
}
