<?php

declare(strict_types=1);

namespace Database\Factories\Cambio;

use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Enums\OrigenCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Cambios del SGSI sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **propuesto**, que es el único estado que no arrastra nada: sin firma, sin
 * plazo obligatorio y sin fechas de implantación ni de cierre, que los `CHECK`
 * acoplan al estado.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<CambioSgsi>
 */
class CambioSgsiFactory extends Factory
{
    protected $model = CambioSgsi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('CS-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 9999)),
            'titulo' => fake()->sentence(),
            'descripcion' => null,
            'ambito' => AmbitoCambio::Proceso->value,
            'origen' => OrigenCambio::Propio->value,
            'estado' => EstadoCambio::Propuesto->value,
            'responsable_id' => null,
            'fecha_propuesta' => Carbon::today(),
            'fecha_prevista' => null,
        ];
    }

    /**
     * En un estado concreto, con todo lo que sus `CHECK` exigen.
     *
     * Lo pone la factory y no cada test, igual que en objetivos: aprobado sin
     * firma o sin plazo no se puede insertar. **El firmante hay que pasarlo** en
     * los estados comprometidos, porque `users` queda fuera de las tres capas y
     * crearlo aquí sería decidir su organización a ciegas.
     */
    public function enEstado(EstadoCambio $estado, ?User $firmante = null, ?Carbon $prevista = null): self
    {
        return $this->state(fn (): array => [
            'estado' => $estado->value,
            'fecha_prevista' => $estado->esComprometido() ? ($prevista ?? Carbon::today()->addMonth()) : $prevista,
            'aprobado_por_id' => $estado->esComprometido() ? $firmante?->id : null,
            'aprobado_en' => $estado->esComprometido() ? Carbon::now() : null,
            'fecha_implantacion' => $estado->estaHecho() ? Carbon::today() : null,
            'fecha_cierre' => $estado->esCerrado() ? Carbon::today() : null,
            'revision' => $estado === EstadoCambio::Revisado ? 'Consiguió lo que pretendía.' : null,
        ]);
    }
}
