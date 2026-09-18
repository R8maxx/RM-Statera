<?php

declare(strict_types=1);

namespace Database\Factories\Contexto;

use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cuestiones del DAFO sintéticas. Ni una real de ningún cliente.
 *
 * Nace **vigente**: sin `analisis_baja_id` y sin motivo, que el `CHECK` acopla en
 * las dos direcciones. El análisis de alta se crea si no se le pasa uno, porque la
 * foránea es obligatoria — una cuestión sin saber desde cuándo existe no es una
 * cuestión del contexto, es una nota.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<CuestionContexto>
 */
class CuestionContextoFactory extends Factory
{
    protected $model = CuestionContexto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('CTX-%02d', fake()->unique()->numberBetween(1, 9999)),
            'tipo' => fake()->randomElement(TipoCuestion::cases())->value,
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->sentence(),
            'materia' => fake()->randomElement(MateriaCuestion::cases())->value,
            'es_climatica' => false,
            'responsable_id' => null,
            'analisis_alta_id' => AnalisisContextoFactory::new(),
            'analisis_baja_id' => null,
            'motivo_baja' => null,
        ];
    }

    public function deTipo(TipoCuestion $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    public function climatica(): self
    {
        return $this->state(fn (): array => [
            'es_climatica' => true,
            'materia' => MateriaCuestion::porDefectoDelClima()->value,
        ]);
    }

    /** Retirada: la baja y su motivo van juntos o el `CHECK` la rechaza. */
    public function retirada(AnalisisContexto $analisis, string $motivo = 'Dejó de ser pertinente.'): self
    {
        return $this->state(fn (): array => [
            'analisis_baja_id' => $analisis->id,
            'motivo_baja' => $motivo,
        ]);
    }
}
