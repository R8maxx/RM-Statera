<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\PlantillaSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos sintéticos.
 *
 * @extends Factory<PlantillaSeccion>
 */
class PlantillaSeccionFactory extends Factory
{
    protected $model = PlantillaSeccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoDocumento::SoaIso->value,
            'seccion' => SeccionNarrativa::Introduccion->value,
            'contenido_md' => 'Texto base de la organización.',
            'actualizado_por_id' => null,
        ];
    }

    public function para(TipoDocumento $tipo, SeccionNarrativa $seccion, string $contenido): self
    {
        return $this->state(fn (): array => [
            'tipo' => $tipo->value,
            'seccion' => $seccion->value,
            'contenido_md' => $contenido,
        ]);
    }

    /**
     * Vacía a conciencia, que NO es lo mismo que ausente: una fila vacía dice
     * «aquí no va nada» y gana al texto de fábrica.
     */
    public function vacia(): self
    {
        return $this->state(fn (): array => ['contenido_md' => '']);
    }
}
