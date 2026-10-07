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

    public function conLimites(?int $cuentas, ?int $sistemas = null): self
    {
        return $this->state(fn (): array => ['limite_cuentas' => $cuentas, 'limite_sistemas' => $sistemas]);
    }
}
