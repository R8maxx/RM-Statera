<?php

declare(strict_types=1);

namespace Database\Factories\Catalogo;

use App\Domain\Catalogo\Models\Refuerzo;
use App\Domain\Catalogo\Models\Requisito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refuerzo>
 */
class RefuerzoFactory extends Factory
{
    protected $model = Refuerzo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisito_id' => Requisito::factory(),
            'codigo' => 'R1',
            'descripcion' => fake()->sentence(),
            'orden' => 1,
        ];
    }

    public function nivel(int $nivel): self
    {
        return $this->state(fn (): array => ['codigo' => 'R'.$nivel, 'orden' => $nivel]);
    }
}
