<?php

declare(strict_types=1);

namespace Database\Factories\Persona;

use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Sesiones de formación y concienciación sintéticas.
 *
 * **La fecha nace dentro de los doce meses de vigencia formativa**, porque es lo
 * que hace que una asistencia cuente: una sesión de hace tres años no saca a nadie
 * de `sinFormacionReciente()`, y un test que la use sin saberlo mediría lo
 * contrario de lo que cree.
 *
 * @extends Factory<AccionFormativa>
 */
class AccionFormativaFactory extends Factory
{
    protected $model = AccionFormativa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('FOR-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 99)),
            'titulo' => fake()->sentence(4),
            'tipo' => fake()->randomElement(TipoAccionFormativa::cases())->value,
            'fecha' => Carbon::today()->subMonths(fake()->numberBetween(0, 6)),
            'duracion_horas' => fake()->randomFloat(2, 0.5, 8),
            'contenido' => null,
            'evidencia_id' => null,
        ];
    }

    public function deTipo(TipoAccionFormativa $tipo): static
    {
        return $this->state(fn (): array => ['tipo' => $tipo->value]);
    }

    /**
     * Fuera de la ventana de vigencia: la sesión que ya no cuenta.
     *
     * El número sale de `Persona::MESES_DE_VIGENCIA_FORMATIVA` y no de un 13
     * escrito a mano: cambiar la cadencia del producto no puede dejar en verde un
     * test que prueba justo lo contrario.
     */
    public function caducada(): static
    {
        return $this->state(fn (): array => [
            'fecha' => Carbon::today()->subMonths(Persona::MESES_DE_VIGENCIA_FORMATIVA + 1),
        ]);
    }
}
