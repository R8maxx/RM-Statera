<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sistema>
 */
class SistemaFactory extends Factory
{
    protected $model = Sistema::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizacion_id' => Organizacion::factory(),
            'marco_id' => Marco::factory(),
            'codigo' => 'SIS-'.fake()->unique()->numerify('####'),
            'nombre' => 'Sistema '.fake()->word(),
            'descripcion' => null,
            'estado' => EstadoSistema::Activo->value,
            'alcance_declarado' => null,
            'exclusiones_justificadas' => null,
            'perfil_id' => null,
        ];
    }

    public function de(Organizacion $organizacion): self
    {
        return $this->state(fn (): array => ['organizacion_id' => $organizacion->id]);
    }

    public function conMarco(Marco $marco): self
    {
        return $this->state(fn (): array => ['marco_id' => $marco->id]);
    }

    public function conPerfil(PerfilCumplimiento $perfil): self
    {
        return $this->state(fn (): array => ['perfil_id' => $perfil->id]);
    }
}
