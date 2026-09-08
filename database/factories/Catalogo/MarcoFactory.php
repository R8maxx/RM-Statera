<?php

declare(strict_types=1);

namespace Database\Factories\Catalogo;

use App\Domain\Catalogo\Enums\EstadoMarco;
use App\Domain\Catalogo\Models\Marco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Marcos sintéticos para los tests.
 *
 * El catálogo real vive en `catalogo/*.yaml` y se carga con `catalogo:importar`.
 * Los tests del motor NO deben depender de él: los tres ficheros llevan
 * `revisado: false` y la matriz cambiará al contrastarla con el BOE.
 *
 * @extends Factory<Marco>
 */
class MarcoFactory extends Factory
{
    protected $model = Marco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'MARCO-'.fake()->unique()->numerify('####'),
            'nombre' => 'Marco de prueba '.fake()->word(),
            'version' => '2022',
            'fecha_vigencia' => '2022-05-05',
            'estado' => EstadoMarco::Vigente->value,
        ];
    }

    public function derogado(): self
    {
        return $this->state(fn (): array => ['estado' => EstadoMarco::Derogado->value]);
    }
}
