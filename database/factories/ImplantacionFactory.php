<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Implantacion>
 */
class ImplantacionFactory extends Factory
{
    protected $model = Implantacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sistema_id' => Sistema::factory(),
            'requisito_id' => Requisito::factory(),
            'aplica' => true,
            'justificacion' => null,
            'exigencia_calculada' => 'aplica',
            'origen_exigencia' => OrigenExigencia::Categoria->value,
            'dimension_moduladora' => null,
            'estado' => EstadoImplantacion::NoIniciado->value,
            'nivel_madurez' => null,
            'responsable_id' => null,
            'fecha_objetivo' => null,
            'notas' => null,
        ];
    }

    public function enEstado(EstadoImplantacion $estado): self
    {
        return $this->state(fn (): array => ['estado' => $estado->value]);
    }
}
