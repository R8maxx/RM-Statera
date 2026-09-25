<?php

declare(strict_types=1);

namespace Database\Factories\Proveedor;

use App\Domain\Proveedor\Models\ClausulaContractual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cláusulas de catálogo para tests. El catálogo de verdad se importa desde
 * `catalogo/clausulas-proveedor.yaml`; esto es para no depender de él en cada
 * test.
 *
 * @extends Factory<ClausulaContractual>
 */
class ClausulaContractualFactory extends Factory
{
    protected $model = ClausulaContractual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9999);

        return [
            'codigo' => "CLX-{$numero}",
            'titulo' => "Cláusula de prueba {$numero}",
            'descripcion' => null,
            'referencias' => [],
            'orden' => $numero,
            'huella' => null,
            'vigente' => true,
            'retirado_en' => null,
        ];
    }
}
