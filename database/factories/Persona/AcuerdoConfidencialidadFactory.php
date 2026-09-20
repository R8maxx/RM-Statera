<?php

declare(strict_types=1);

namespace Database\Factories\Persona;

use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Acuerdos de confidencialidad sintéticos: `mp.per.2`.
 *
 * **Nace sin fecha de caducidad, y eso significa algo**: un acuerdo de
 * confidencialidad normalmente no vence, porque el deber sobrevive a la relación
 * laboral. El vacío es la respuesta correcta y no un descuido.
 *
 * @extends Factory<AcuerdoConfidencialidad>
 */
class AcuerdoConfidencialidadFactory extends Factory
{
    protected $model = AcuerdoConfidencialidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha_firma' => Carbon::today()->subMonths(fake()->numberBetween(1, 48)),
            'vigente_hasta' => null,
            'nota' => null,
            'evidencia_id' => null,
        ];
    }

    /** Caducado: lo que deja a la persona en `sinAcuerdoVigente()`. */
    public function caducado(): static
    {
        return $this->state(fn (array $atributos): array => [
            'vigente_hasta' => Carbon::parse($atributos['fecha_firma'])->addDay(),
        ]);
    }
}
