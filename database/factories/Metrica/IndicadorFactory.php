<?php

declare(strict_types=1);

namespace Database\Factories\Metrica;

use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Indicadores sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **manual** y no calculado, que es lo contrario de lo que se espera: un
 * indicador calculado obliga a que exista todo lo que su cálculo consulta
 * —implantaciones, activos, riesgos— y la mayoría de los tests de este módulo
 * van de la serie, no de la fuente. El estado `calculado()` lo cambia en una
 * línea para los que sí.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`, y
 * declararlo aquí lo cortocircuitaría —el trait sólo rellena si viene a nulo— y
 * la fila nacería con el tenant equivocado. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Indicador>
 */
class IndicadorFactory extends Factory
{
    protected $model = Indicador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('IND-%02d', fake()->unique()->numberBetween(1, 9999)),
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->sentence(),
            'origen' => OrigenMedicion::Manual->value,
            'calculo' => null,
            'formula_o_fuente' => 'Recuento manual sobre la hoja de registro del servicio.',
            'marco_id' => null,
            'unidad' => UnidadIndicador::Recuento->value,
            'sentido' => SentidoIndicador::MenorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
            'objetivo' => null,
            'responsable_id' => null,
            'activo' => true,
        ];
    }

    /** Un indicador que Statera calcula solo, con la unidad y el sentido naturales. */
    public function calculado(CalculoIndicador $calculo): self
    {
        return $this->state(fn (): array => [
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => $calculo->value,
            // El `CHECK` de la tabla los acopla en las dos direcciones: un
            // calculado con método escrito miente sobre de dónde sale su cifra.
            'formula_o_fuente' => null,
            'unidad' => $calculo->unidad()->value,
            'sentido' => $calculo->sentido()->value,
        ]);
    }

    public function conObjetivo(float $objetivo, SentidoIndicador $sentido = SentidoIndicador::MayorMejor): self
    {
        return $this->state(fn (): array => [
            'objetivo' => $objetivo,
            'sentido' => $sentido->value,
        ]);
    }

    public function conPeriodicidad(Periodicidad $periodicidad): self
    {
        return $this->state(fn (): array => ['periodicidad' => $periodicidad->value]);
    }

    public function retirado(): self
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
