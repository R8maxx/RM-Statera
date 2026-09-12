<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tarea\Models\Subtarea;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Datos sintéticos. Ni un paso real de ningún cliente.
 *
 * @extends Factory<Subtarea>
 */
class SubtareaFactory extends Factory
{
    protected $model = Subtarea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => 'Paso '.fake()->unique()->numberBetween(1, 9999),
            'hecha_en' => null,
            'orden' => 0,
        ];
    }

    public function hecha(): self
    {
        return $this->state(fn (): array => ['hecha_en' => Carbon::now()]);
    }

    public function enPosicion(int $orden): self
    {
        return $this->state(fn (): array => ['orden' => $orden]);
    }
}
