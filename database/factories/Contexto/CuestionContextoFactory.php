<?php

declare(strict_types=1);

namespace Database\Factories\Contexto;

use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cuestiones del DAFO sintéticas. Ni una real de ningún cliente.
 *
 * Nace **vigente**: sin `analisis_baja_id` y sin motivo, que el `CHECK` acopla en
 * las dos direcciones.
 *
 * **El análisis de alta se resuelve con `AnalisisEnCurso`, no acuñando uno nuevo.**
 * Es la misma regla que el producto: hay un borrador como mucho por organización
 * —lo garantiza el índice único parcial `analisis_contexto_borrador_unico`— y el
 * primer gesto de escritura lo estrena. Con `AnalisisContextoFactory::new()` en la
 * definición, la **segunda** cuestión de un test acuñaba un segundo borrador y
 * moría con una violación de índice único que no menciona la palabra «análisis»;
 * y aunque la base lo hubiera admitido, un DAFO repartido entre tres borradores no
 * es un escenario que pueda darse por la interfaz, así que el test no probaría
 * nada. Pasar `analisis_alta_id` a mano sigue valiendo —ahí es una decisión
 * explícita de quien escribe el test— y entonces el borrador ni se estrena.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<CuestionContexto>
 */
class CuestionContextoFactory extends Factory
{
    protected $model = CuestionContexto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('CTX-%02d', fake()->unique()->numberBetween(1, 9999)),
            'tipo' => fake()->randomElement(TipoCuestion::cases())->value,
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->sentence(),
            'materia' => fake()->randomElement(MateriaCuestion::cases())->value,
            'es_climatica' => false,
            'responsable_id' => null,
            'analisis_alta_id' => fn (): int => app(AnalisisEnCurso::class)->borradorObligatorio()->id,
            'analisis_baja_id' => null,
            'motivo_baja' => null,
        ];
    }

    public function deTipo(TipoCuestion $tipo): self
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    public function climatica(): self
    {
        return $this->state(fn (): array => [
            'es_climatica' => true,
            'materia' => MateriaCuestion::porDefectoDelClima()->value,
        ]);
    }

    /** Retirada: la baja y su motivo van juntos o el `CHECK` la rechaza. */
    public function retirada(AnalisisContexto $analisis, string $motivo = 'Dejó de ser pertinente.'): self
    {
        return $this->state(fn (): array => [
            'analisis_baja_id' => $analisis->id,
            'motivo_baja' => $motivo,
        ]);
    }
}
