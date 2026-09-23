<?php

declare(strict_types=1);

namespace Database\Factories\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Pruebas de continuidad sintéticas. Ninguna real de ningún cliente.
 *
 * Nace **planificada**, colgada de un plan de continuidad propio —no de uno
 * que el test tenga que crear aparte— y con una fecha prevista a treinta días
 * vista, que es el estado que no arrastra ningún `CHECK`: el de `realizada`
 * exige fecha de realización y resultado, y el de `cancelada` exige motivo.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde
 * el contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<PruebaContinuidad>
 */
class PruebaContinuidadFactory extends Factory
{
    protected $model = PruebaContinuidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('PC-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 99)),
            'titulo' => fake()->sentence(4),
            'documento_id' => Documento::factory()->planContinuidad(),
            'tipo' => TipoPrueba::Sobremesa->value,
            'estado' => EstadoPrueba::Planificada->value,
            'fecha_prevista' => Carbon::today()->addDays(30),
            'fecha_realizacion' => null,
            'resultado' => null,
            'conclusiones' => null,
            'motivo_cancelacion' => null,
            'evidencia_id' => null,
            'responsable_id' => null,
        ];
    }

    public function deDocumento(Documento $documento): static
    {
        return $this->state(fn (): array => ['documento_id' => $documento->id]);
    }

    /**
     * En un estado concreto, con lo que su `CHECK` de coherencia exige.
     *
     * Misma idea que `BiaServicioFactory::enEstado()` y
     * `IncidenteFactory::enEstado()`: la restricción acoplada es la que más se
     * olvida, y por eso la pone la factory y no cada test.
     */
    public function planificada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoPrueba::Planificada->value,
            'fecha_realizacion' => null,
            'resultado' => null,
            'motivo_cancelacion' => null,
        ]);
    }

    public function realizada(?ResultadoPrueba $resultado = null): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoPrueba::Realizada->value,
            'fecha_realizacion' => Carbon::today(),
            'resultado' => ($resultado ?? ResultadoPrueba::Superada)->value,
            'conclusiones' => fake()->paragraph(),
            'motivo_cancelacion' => null,
        ]);
    }

    public function cancelada(?string $motivo = null): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoPrueba::Cancelada->value,
            'motivo_cancelacion' => $motivo ?? 'Se pospone por indisponibilidad del proveedor del centro alternativo.',
            'fecha_realizacion' => null,
            'resultado' => null,
        ]);
    }
}
