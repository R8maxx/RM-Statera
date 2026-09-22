<?php

declare(strict_types=1);

namespace Database\Factories\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Compromisos sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **sin obligación de catálogo y sin sistema**: es el compromiso propio, el
 * único que no depende de que exista nada más. Y `computa_desde` cae un año atrás
 * para que la próxima fecha sea hoy y el compromiso nazca justo en el filo —lo
 * que hace que un test que quiera «vencido» o «en plazo» tenga que decirlo, en
 * vez de heredarlo de un valor arbitrario de la factory.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Compromiso>
 */
class CompromisoFactory extends Factory
{
    protected $model = Compromiso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obligacion_id' => null,
            'sistema_id' => null,
            'codigo' => sprintf('OBL-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 9999)),
            'titulo' => fake()->sentence(4),
            'descripcion' => null,
            'periodicidad_meses' => 12,
            'computa_desde' => Carbon::today()->subYear(),
            'responsable_id' => null,
            'activo' => true,
            'notas' => null,
        ];
    }

    public function deObligacion(int $obligacionId): self
    {
        return $this->state(fn (): array => ['obligacion_id' => $obligacionId]);
    }

    public function deSistema(?int $sistemaId): self
    {
        return $this->state(fn (): array => ['sistema_id' => $sistemaId]);
    }

    public function cada(int $meses): self
    {
        return $this->state(fn (): array => ['periodicidad_meses' => $meses]);
    }

    /** Sin cumplimientos y con el reloj corrido: la próxima fecha ya pasó. */
    public function vencido(int $dias = 30): self
    {
        return $this->state(fn (array $atributos): array => [
            'computa_desde' => Carbon::today()->subMonthsNoOverflow($atributos['periodicidad_meses'])->subDays($dias),
        ]);
    }

    /** La próxima fecha cae dentro de `$dias`. */
    public function venceEn(int $dias): self
    {
        return $this->state(fn (array $atributos): array => [
            'computa_desde' => Carbon::today()->addDays($dias)->subMonthsNoOverflow($atributos['periodicidad_meses']),
        ]);
    }

    public function retirado(): self
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
