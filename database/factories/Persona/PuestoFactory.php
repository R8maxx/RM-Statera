<?php

declare(strict_types=1);

namespace Database\Factories\Persona;

use App\Domain\Persona\Models\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Puesto>
 */
class PuestoFactory extends Factory
{
    protected $model = Puesto::class;

    /**
     * `organizacion_id` NO se declara: lo rellena `PerteneceAOrganizacion` en el
     * evento `creating`, y sólo si viene a nulo. Un valor por defecto aquí lo
     * cortocircuita y la fila nace con un tenant que no es el del contexto, que
     * el `WITH CHECK` de la política rechaza con un error de privilegios que no
     * menciona la palabra «organización». Lo clava `FactoriesSinOrganizacionTest`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('PUE-%03d', fake()->unique()->numberBetween(1, 999)),
            'titulo' => fake()->unique()->jobTitle(),
            'reporta_a_id' => null,
            'mision' => null,
            'funciones' => null,
            'competencias' => null,
        ];
    }

    /** Con la caracterización que mira `mp.per.1`. */
    public function caracterizado(): static
    {
        return $this->state(fn (): array => [
            'mision' => 'Sostener el servicio y responder de su disponibilidad.',
            'funciones' => "Operar la plataforma.\nAtender las incidencias de su ámbito.",
            'competencias' => 'Titulación técnica o tres años de experiencia equivalente.',
        ]);
    }

    /** Cuelga de otro puesto. */
    public function bajo(Puesto $superior): static
    {
        return $this->state(fn (): array => ['reporta_a_id' => $superior->id]);
    }
}
