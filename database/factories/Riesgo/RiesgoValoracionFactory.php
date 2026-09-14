<?php

declare(strict_types=1);

namespace Database\Factories\Riesgo;

use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Una valoración con la escala de fábrica congelada dentro.
 *
 * La escala **se congela también en los tests**, y no es ceremonia: es lo que
 * hace que un test de histórico pruebe el histórico de verdad. Una factoría que
 * dejara `escala` vacía haría pasar tests que en producción reventarían al releer
 * la valoración.
 *
 * @extends Factory<RiesgoValoracion>
 */
class RiesgoValoracionFactory extends Factory
{
    protected $model = RiesgoValoracion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $probabilidad = 3;
        $impacto = 3;

        return [
            'riesgo_id' => Riesgo::factory(),
            'vigente' => true,
            'probabilidad' => $probabilidad,
            'impacto' => $impacto,
            'impacto_por_dimension' => [],
            'riesgo_intrinseco' => $probabilidad * $impacto,
            'probabilidad_residual' => null,
            'impacto_residual' => null,
            'riesgo_residual' => null,
            'justificacion_residual' => null,
            'decision' => DecisionRiesgo::Mitigar->value,
            'escala' => MetodologiaDeFabrica::metodologia()->aArray(),
            'salvaguardas' => [],
            'valorada_por_id' => null,
            'valorada_en' => Carbon::today(),
            'aceptada_por_id' => null,
            'aceptada_en' => null,
            'nota' => null,
            'nota_aceptacion' => null,
        ];
    }

    public function con(int $probabilidad, int $impacto): self
    {
        return $this->state(fn (): array => [
            'probabilidad' => $probabilidad,
            'impacto' => $impacto,
            'riesgo_intrinseco' => $probabilidad * $impacto,
        ]);
    }

    /**
     * Con residual declarado.
     *
     * El `CHECK` no deja que supere al intrínseco: una salvaguarda no empeora un
     * riesgo, y un número mayor es un error de captura.
     */
    public function conResidual(int $probabilidad, int $impacto, ?string $justificacion = null): self
    {
        return $this->state(fn (): array => [
            'probabilidad_residual' => $probabilidad,
            'impacto_residual' => $impacto,
            'riesgo_residual' => $probabilidad * $impacto,
            'justificacion_residual' => $justificacion,
        ]);
    }

    public function conDecision(DecisionRiesgo $decision): self
    {
        return $this->state(fn (): array => ['decision' => $decision->value]);
    }

    /** Firmada. A partir de aquí el trigger la vuelve inmutable. */
    public function aceptada(?User $por = null, ?Carbon $cuando = null): self
    {
        return $this->state(fn (): array => [
            'aceptada_por_id' => $por instanceof User ? $por->id : User::factory(),
            'aceptada_en' => $cuando ?? Carbon::today(),
        ]);
    }

    /**
     * Jubilada: la que dejó de ser la vigente.
     *
     * El índice único parcial sólo admite una vigente por riesgo, así que un test
     * con histórico necesita que todas menos una pasen por aquí.
     */
    public function jubilada(): self
    {
        return $this->state(fn (): array => ['vigente' => false]);
    }
}
