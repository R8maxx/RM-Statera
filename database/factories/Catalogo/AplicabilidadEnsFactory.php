<?php

declare(strict_types=1);

namespace Database\Factories\Catalogo;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Requisito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una celda de la matriz del Anexo II.
 *
 * @extends Factory<AplicabilidadEns>
 */
class AplicabilidadEnsFactory extends Factory
{
    protected $model = AplicabilidadEns::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requisito_id' => Requisito::factory(),
            'categoria' => CategoriaEns::Basica->value,
            'exigencia' => 'aplica',
            'dimension_moduladora' => null,
        ];
    }

    public function para(CategoriaEns $categoria, string $exigencia): self
    {
        return $this->state(fn (): array => [
            'categoria' => $categoria->value,
            'exigencia' => $exigencia,
        ]);
    }

    /**
     * La exigencia la dicta el nivel de esta dimensión, no la categoría global
     * del sistema.
     */
    public function moduladaPor(Dimension $dimension): self
    {
        return $this->state(fn (): array => ['dimension_moduladora' => $dimension->value]);
    }
}
