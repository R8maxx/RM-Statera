<?php

declare(strict_types=1);

namespace Database\Factories\Metrica;

use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Mediciones sintéticas.
 *
 * El periodo por defecto es el **trimestre cerrado más reciente**, que es el que
 * mide el comando: una medición del trimestre en curso dejaría a
 * `tienePeriodoSinMedir()` diciendo que falta la del anterior, y la mitad de los
 * tests de este módulo van justo de eso.
 *
 * `organizacion_id` no se declara, por lo mismo que en `IndicadorFactory`.
 *
 * @extends Factory<Medicion>
 */
class MedicionFactory extends Factory
{
    protected $model = Medicion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

        return [
            'indicador_id' => Indicador::factory(),
            'periodo_inicio' => $inicio,
            'periodo_fin' => $fin,
            'medida_en' => $fin,
            'valor' => fake()->numberBetween(0, 100),
            'numerador' => null,
            'denominador' => null,
            'objetivo' => null,
            'origen' => OrigenMedicion::Manual->value,
            'nota' => null,
            'registrada_por_id' => null,
        ];
    }

    /** En un periodo concreto, resuelto con la periodicidad que toque. */
    public function enPeriodo(CarbonInterface $fecha, Periodicidad $periodicidad = Periodicidad::Trimestral): self
    {
        [$inicio, $fin] = $periodicidad->periodoDe($fecha);

        return $this->state(fn (): array => [
            'periodo_inicio' => $inicio,
            'periodo_fin' => $fin,
            'medida_en' => $fin,
        ]);
    }

    public function con(float $valor, ?float $objetivo = null): self
    {
        return $this->state(fn (): array => [
            'valor' => $valor,
            'objetivo' => $objetivo,
        ]);
    }
}
