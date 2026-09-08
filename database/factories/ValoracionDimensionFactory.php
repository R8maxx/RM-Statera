<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValoracionDimension>
 */
class ValoracionDimensionFactory extends Factory
{
    protected $model = ValoracionDimension::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sistema_id' => Sistema::factory(),
            'dimension' => Dimension::Confidencialidad->value,
            'nivel' => NivelDimension::Bajo->value,
            'justificacion' => null,
        ];
    }

    public function para(Dimension $dimension, NivelDimension $nivel): self
    {
        return $this->state(fn (): array => [
            'dimension' => $dimension->value,
            'nivel' => $nivel->value,
        ]);
    }
}
