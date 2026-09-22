<?php

declare(strict_types=1);

namespace Database\Factories\Obligacion;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Obligacion\Enums\ReferenciaCumplimiento;
use App\Domain\Obligacion\Models\Obligacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Obligaciones del catálogo, sintéticas.
 *
 * **Sin `organizacion_id` porque la tabla no lo tiene**: es catálogo global
 * (invariante 2). No es el mismo motivo por el que no lo declaran las demás
 * factories —allí lo rellena `PerteneceAOrganizacion`—, y conviene no confundirlo:
 * aquí la columna no existe.
 *
 * Nace **sin marco y sin categoría mínima**, que es la obligación que se propone
 * a todo el mundo. Los dos filtros de `ObligacionesAplicables` se ejercitan con
 * los states.
 *
 * @extends Factory<Obligacion>
 */
class ObligacionFactory extends Factory
{
    protected $model = Obligacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'obl.'.fake()->unique()->slug(2),
            'marco_id' => null,
            'nombre' => fake()->sentence(4),
            'descripcion' => null,
            'base_legal' => null,
            'periodicidad_meses_sugerida' => 12,
            'categoria_minima' => null,
            'referencia_sugerida' => null,
            'orden' => 0,
            'huella' => null,
            'vigente' => true,
            'retirado_en' => null,
        ];
    }

    public function deMarco(int $marcoId): self
    {
        return $this->state(fn (): array => ['marco_id' => $marcoId]);
    }

    public function desdeCategoria(CategoriaEns $categoria): self
    {
        return $this->state(fn (): array => ['categoria_minima' => $categoria->value]);
    }

    public function cada(int $meses): self
    {
        return $this->state(fn (): array => ['periodicidad_meses_sugerida' => $meses]);
    }

    public function conReferencia(ReferenciaCumplimiento $referencia): self
    {
        return $this->state(fn (): array => ['referencia_sugerida' => $referencia->value]);
    }

    public function retirada(): self
    {
        return $this->state(fn (): array => ['vigente' => false, 'retirado_en' => now()]);
    }
}
