<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Organizaciones sintéticas. Nada de datos reales de ningún cliente: el proyecto
 * es personal y la separación se mantiene explícita también en los fixtures.
 *
 * @extends Factory<Organizacion>
 */
class OrganizacionFactory extends Factory
{
    protected $model = Organizacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'cif' => 'B'.fake()->unique()->numerify('########'),
            'sector' => fake()->word(),
            'sujeto_obligado_ens' => false,
            'proveedor_sector_publico' => false,
            'activa' => true,
        ];
    }

    public function sujetoObligado(): self
    {
        return $this->state(fn (): array => ['sujeto_obligado_ens' => true]);
    }

    public function proveedorPublico(): self
    {
        return $this->state(fn (): array => ['proveedor_sector_publico' => true]);
    }
}
