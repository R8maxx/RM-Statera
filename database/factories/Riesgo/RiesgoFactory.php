<?php

declare(strict_types=1);

namespace Database\Factories\Riesgo;

use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Riesgos sintéticos. Ni uno real de ningún cliente.
 *
 * Nace con amenaza del catálogo y sin `amenaza_libre`, que es una de las dos
 * únicas combinaciones que el `CHECK` admite: las dos a la vez es un riesgo que
 * dice ser dos cosas, y ninguna no es un riesgo.
 *
 * @extends Factory<Riesgo>
 */
class RiesgoFactory extends Factory
{
    protected $model = Riesgo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9999);

        return [
            'codigo' => sprintf('R-%04d', $numero),
            'titulo' => "Riesgo de prueba {$numero}",
            'amenaza_id' => Amenaza::factory(),
            'amenaza_libre' => null,
            'vulnerabilidad' => null,
            'propietario_id' => null,
            'fecha_revision' => null,
            'notas' => null,
        ];
    }

    public function conAmenaza(Amenaza $amenaza): self
    {
        return $this->state(fn (): array => [
            'amenaza_id' => $amenaza->id,
            'amenaza_libre' => null,
        ]);
    }

    /** Una amenaza que no está en MAGERIT, como «el proveedor X cierra». */
    public function conAmenazaLibre(string $texto): self
    {
        return $this->state(fn (): array => [
            'amenaza_id' => null,
            'amenaza_libre' => $texto,
        ]);
    }

    public function de(User $propietario): self
    {
        return $this->state(fn (): array => ['propietario_id' => $propietario->id]);
    }

    public function conRevision(string|Carbon $fecha): self
    {
        return $this->state(fn (): array => ['fecha_revision' => $fecha]);
    }

    public function revisionVencida(int $dias = 1): self
    {
        return $this->state(fn (): array => ['fecha_revision' => Carbon::today()->subDays($dias)]);
    }
}
