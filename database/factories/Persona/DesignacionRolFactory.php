<?php

declare(strict_types=1);

namespace Database\Factories\Persona;

use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Models\DesignacionRol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Nombramientos ENS sintéticos.
 *
 * **Nace con el rol que no es único por sistema**, `ResponsableInformacion`: es el
 * que no choca con el índice único parcial cuando un test crea varios de golpe. El
 * que se quiera distinto se pide con `enRol()`, que es donde el test decide.
 *
 * `persona_id` y `sistema_id` no se declaran: la factory no inventa el padre de una
 * designación, porque la incompatibilidad del 5.3 sólo significa algo entre filas
 * de la misma persona y el mismo sistema.
 *
 * @extends Factory<DesignacionRol>
 */
class DesignacionRolFactory extends Factory
{
    protected $model = DesignacionRol::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rol' => RolEns::ResponsableInformacion->value,
            'desde' => Carbon::today()->subMonths(fake()->numberBetween(1, 24)),
            'hasta' => null,
            'designada_por_id' => null,
            'nota' => null,
        ];
    }

    public function enRol(RolEns $rol): static
    {
        return $this->state(fn (): array => ['rol' => $rol->value]);
    }

    /** Revocado: `hasta` puesto. Vigente es `hasta IS NULL`. */
    public function revocada(?Carbon $hasta = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'hasta' => $hasta ?? Carbon::parse($atributos['desde'])->addMonths(1),
        ]);
    }
}
