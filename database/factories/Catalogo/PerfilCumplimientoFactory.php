<?php

declare(strict_types=1);

namespace Database\Factories\Catalogo;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Perfiles de cumplimiento sintéticos.
 *
 * Los reales son los de la serie CCN-STIC 890 y todavía no están cargados en
 * `catalogo/`; el esquema y el importador ya los soportan.
 *
 * @extends Factory<PerfilCumplimiento>
 */
class PerfilCumplimientoFactory extends Factory
{
    protected $model = PerfilCumplimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marco_id' => Marco::factory(),
            'codigo' => 'PERFIL-'.fake()->unique()->numerify('####'),
            'nombre' => 'Perfil de prueba '.fake()->word(),
            'descripcion' => null,
            'referencia' => null,
        ];
    }
}
