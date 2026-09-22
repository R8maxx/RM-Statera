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
            // La razón social se deja NULA a propósito, que es el estado de toda
            // organización anterior a la ficha: así `nombreLegal()` cae a
            // `nombre` y los tests de contenido de los seis documentos siguen
            // afirmando exactamente lo que afirmaban.
            'razon_social' => null,
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

    /** Con la identificación legal rellena, para lo que se imprime en un documento. */
    public function conRazonSocial(?string $razonSocial = null): self
    {
        return $this->state(fn (array $atributos): array => [
            'razon_social' => $razonSocial ?? $atributos['nombre'].', S.L.',
        ]);
    }
}
