<?php

declare(strict_types=1);

namespace Database\Factories\Catalogo;

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Requisito>
 */
class RequisitoFactory extends Factory
{
    protected $model = Requisito::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marco_id' => Marco::factory(),
            'codigo' => 'req.'.fake()->unique()->numerify('####'),
            'tipo' => TipoRequisito::Medida->value,
            'parent_id' => null,
            'titulo' => fake()->sentence(4),
            'descripcion' => null,
            'orden' => fake()->numberBetween(1, 999),
            'atributos' => [],
            'vigente' => true,
        ];
    }

    public function conCodigo(string $codigo): self
    {
        return $this->state(fn (): array => ['codigo' => $codigo]);
    }

    public function clausula(): self
    {
        return $this->state(fn (): array => ['tipo' => TipoRequisito::Clausula->value]);
    }

    public function control(): self
    {
        return $this->state(fn (): array => ['tipo' => TipoRequisito::Control->value]);
    }

    public function hijoDe(Requisito $padre): self
    {
        return $this->state(fn (): array => [
            'marco_id' => $padre->marco_id,
            'parent_id' => $padre->id,
        ]);
    }

    /**
     * Un requisito que desapareció de una revisión del marco. No se borra: se
     * marca, porque puede haber implantaciones colgando de él.
     */
    public function retirado(): self
    {
        return $this->state(fn (): array => [
            'vigente' => false,
            'retirado_en' => Carbon::now(),
        ]);
    }
}
