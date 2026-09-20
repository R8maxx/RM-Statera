<?php

declare(strict_types=1);

namespace Database\Factories\Mejora;

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Oportunidades de mejora sintéticas. Ni una real de ningún cliente.
 *
 * Nace **propuesta y de iniciativa propia**, que es el único estado que no
 * arrastra nada: sin hallazgo detrás —el `CHECK` sólo admite hallazgo con origen
 * `auditoria`— y sin fecha de cierre, que el `CHECK` de coherencia acopla al
 * estado.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Mejora>
 */
class MejoraFactory extends Factory
{
    protected $model = Mejora::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('OM-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 9999)),
            'origen' => OrigenMejora::Propia->value,
            'hallazgo_id' => null,
            'titulo' => fake()->sentence(),
            'descripcion' => null,
            'beneficio_esperado' => null,
            'estado' => EstadoMejora::Propuesta->value,
            'responsable_id' => null,
            'fecha_deteccion' => Carbon::today(),
            'fecha_prevista' => null,
            'fecha_cierre' => null,
        ];
    }

    /** De un hallazgo, que es el único origen que admite tenerlo. */
    public function deHallazgo(int $hallazgoId): self
    {
        return $this->state(fn (): array => [
            'origen' => OrigenMejora::Auditoria->value,
            'hallazgo_id' => $hallazgoId,
        ]);
    }

    /**
     * En un estado concreto, con la fecha de cierre que su `CHECK` exige.
     *
     * La pone la factory y no cada test, igual que en tareas, auditorías, no
     * conformidades y objetivos: cerrada sin fecha no se puede insertar.
     */
    public function enEstado(EstadoMejora $estado, ?Carbon $fecha = null): self
    {
        return $this->state(fn (): array => [
            'estado' => $estado->value,
            'fecha_cierre' => $estado->esCerrada() ? ($fecha ?? Carbon::today()) : null,
        ]);
    }

    /** Abierta y con la fecha prevista ya pasada. No es un rojo: ver el enum. */
    public function pasadaDeFecha(): self
    {
        return $this->state(fn (): array => ['fecha_prevista' => Carbon::today()->subDays(15)]);
    }
}
