<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Plataforma\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Planes sintéticos, sin límites salvo que el test los ponga.
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->slug(2),
            'nombre' => fake()->unique()->words(2, true),
            'limite_cuentas' => null,
            'limite_sistemas' => null,
            'dias_gracia' => 15,
            'activo' => true,
        ];
    }

    /** Que la organización lo contrate por su cuenta (punto 51), con precio al mes en céntimos. */
    public function contratable(int $precioMensualCentimos = 4900, int $descuentoAnual = 0): self
    {
        return $this->state(fn (): array => [
            'contratable' => true,
            'precio_mensual_centimos' => $precioMensualCentimos,
            'descuento_anual' => $descuentoAnual,
        ]);
    }

    public function conLimites(?int $cuentas, ?int $sistemas = null): self
    {
        return $this->state(fn (): array => ['limite_cuentas' => $cuentas, 'limite_sistemas' => $sistemas]);
    }
}
