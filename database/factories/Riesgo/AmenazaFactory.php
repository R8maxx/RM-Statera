<?php

declare(strict_types=1);

namespace Database\Factories\Riesgo;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Riesgo\Enums\GrupoAmenaza;
use App\Domain\Riesgo\Models\Amenaza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Amenazas sintéticas para los tests.
 *
 * Las de verdad las carga `catalogo:importar` desde
 * `catalogo/magerit-amenazas.yaml`, que es el catálogo global y versionado. Esta
 * factoría existe para los tests que necesitan una amenaza cualquiera sin montar
 * el catálogo entero, y por eso los códigos no se parecen a los de MAGERIT: que
 * nadie confunda una fila de prueba con una del catálogo.
 *
 * **Sin `organizacion_id`**: la tabla es global (invariante 2).
 *
 * @extends Factory<Amenaza>
 */
class AmenazaFactory extends Factory
{
    protected $model = Amenaza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 9999);

        return [
            'codigo' => "X.{$numero}",
            'grupo' => GrupoAmenaza::ErroresNoIntencionados->value,
            'nombre' => "Amenaza de prueba {$numero}",
            'descripcion' => null,
            'dimensiones' => [Dimension::Disponibilidad->value],
            'tipos_activo' => [TipoActivo::Hardware->value],
            'orden' => $numero,
            'huella' => null,
            'vigente' => true,
            'retirado_en' => null,
        ];
    }

    public function delGrupo(GrupoAmenaza $grupo): self
    {
        return $this->state(fn (): array => ['grupo' => $grupo->value]);
    }

    /** @param list<Dimension> $dimensiones */
    public function sobre(array $dimensiones): self
    {
        return $this->state(fn (): array => [
            'dimensiones' => array_map(static fn (Dimension $dimension): string => $dimension->value, $dimensiones),
        ]);
    }

    /** @param list<TipoActivo> $tipos */
    public function paraTipos(array $tipos): self
    {
        return $this->state(fn (): array => [
            'tipos_activo' => array_map(static fn (TipoActivo $tipo): string => $tipo->value, $tipos),
        ]);
    }

    /** Retirada de una revisión del catálogo: no se borra, se marca. */
    public function retirada(): self
    {
        return $this->state(fn (): array => ['vigente' => false, 'retirado_en' => now()]);
    }
}
