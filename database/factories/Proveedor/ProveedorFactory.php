<?php

declare(strict_types=1);

namespace Database\Factories\Proveedor;

use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Enums\UbicacionDatos;
use App\Domain\Proveedor\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Proveedores sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **en evaluación, sin activos y con la criticidad declarada media**: sin
 * activos no hay derivada, y el `CHECK` exige una de las dos.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`. Lo clava
 * `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 999);

        return [
            'codigo' => sprintf('PRV-%03d', $numero),
            'nombre' => "Proveedor sintético {$numero}",
            'cif' => null,
            'servicio_prestado' => 'Servicio de prueba.',
            'estado' => EstadoProveedor::EnEvaluacion->value,
            'criticidad_derivada' => null,
            'criticidad_declarada' => Criticidad::Media->value,
            'justificacion_criticidad' => null,
            'es_nube' => false,
            'modelo_nube' => null,
            'ubicacion_datos' => UbicacionDatos::UeEee->value,
            'ubicacion_detalle' => null,
            'es_subencargado_rgpd' => false,
            'responsable_id' => null,
            'notas' => null,
            'proxima_evaluacion' => null,
        ];
    }

    public function conCriticidad(Criticidad $criticidad): static
    {
        return $this->state(fn (): array => ['criticidad_declarada' => $criticidad->value]);
    }
}
