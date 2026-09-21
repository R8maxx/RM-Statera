<?php

declare(strict_types=1);

namespace Database\Factories\Persona;

use App\Domain\Persona\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Personas sintéticas. Ni una real de ningún cliente.
 *
 * Nace **activa y sin cuenta**, que es el caso mayoritario en una plantilla: la
 * mayoría de la gente no entra nunca en Statera. `user_id` es el puente y se pone
 * con `conCuenta()`.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Declararlo aquí lo cortocircuitaría —el trait sólo rellena si viene a
 * nulo— y el `WITH CHECK` de la política rechazaría la fila con un error de
 * privilegios que no menciona la palabra «organización». Lo clava
 * `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('PER-%03d', fake()->unique()->numberBetween(1, 999)),

            /*
             * `nombre` NO se declara: es una columna generada y escribirla haría
             * que PostgreSQL rechazara el `INSERT` entero. Sale de estas tres.
             */
            'nombre_pila' => fake()->firstName(),
            'apellido1' => fake()->lastName(),
            'apellido2' => fake()->lastName(),

            // Sintético y con la forma de un NIF, sin serlo: la letra no se
            // valida en ningún sitio y aquí tampoco se finge que cuadre.
            'nif' => sprintf('%08dX', fake()->unique()->numberBetween(1, 99999999)),
            'telefono' => null,
            'telefono_fijo' => null,
            'direccion' => null,
            'fecha_nacimiento' => null,

            'email' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'fecha_alta' => Carbon::today()->subMonths(fake()->numberBetween(1, 60)),
            'fecha_baja' => null,
            'notas' => null,
        ];
    }

    /**
     * Ya no está en plantilla.
     *
     * La baja va **después** del alta porque el `CHECK` lo exige, y ponerla a
     * ciegas daría un error de restricción que no dice que las fechas están al
     * revés.
     */
    public function deBaja(?Carbon $fecha = null): static
    {
        return $this->state(fn (array $atributos): array => [
            'fecha_baja' => $fecha ?? Carbon::parse($atributos['fecha_alta'])->addMonths(1),
        ]);
    }

    /** Con cuenta de Statera: el puente entre los dos mundos. */
    public function conCuenta(int $userId): static
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }
}
