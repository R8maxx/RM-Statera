<?php

declare(strict_types=1);

namespace Database\Factories\Continuidad;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Models\BiaServicio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * BIA sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **en borrador, sin umbral intolerable** —los cinco tramos en `bajo`— y
 * con un RTO que no puede ser incoherente con nada: es el estado que no
 * arrastra ningún `CHECK` ni ninguna regla del dominio.
 *
 * `activo_id` crea su propio servicio de tipo `TipoActivo::Servicios` por
 * defecto, porque un BIA sin activo no se puede persistir y casi ningún test
 * necesita elegir cuál.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde
 * el contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<BiaServicio>
 */
class BiaServicioFactory extends Factory
{
    protected $model = BiaServicio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activo_id' => Activo::factory()->deTipo(TipoActivo::Servicios),
            'impacto_4h' => NivelImpacto::Bajo->value,
            'impacto_1d' => NivelImpacto::Bajo->value,
            'impacto_3d' => NivelImpacto::Bajo->value,
            'impacto_1s' => NivelImpacto::Bajo->value,
            'impacto_1m' => NivelImpacto::Bajo->value,
            'rto_horas' => 24,
            'rpo_horas' => 4,
            'justificacion' => null,
            'estado' => EstadoBia::Borrador->value,
            'responsable_id' => null,
            'aprobado_por_id' => null,
            'fecha_aprobacion' => null,
            'fecha_revision' => null,
        ];
    }

    public function deActivo(Activo $activo): static
    {
        return $this->state(fn (): array => ['activo_id' => $activo->id]);
    }

    /**
     * En un estado concreto, con lo que su `CHECK` de coherencia exige.
     *
     * Misma idea que `IncidenteFactory::enEstado()`: la restricción acoplada
     * es la que más se olvida, y por eso la pone la factory y no cada test.
     */
    public function enEstado(EstadoBia $estado): static
    {
        return $this->state(fn (): array => [
            'estado' => $estado->value,
            'aprobado_por_id' => null,
            'fecha_aprobacion' => $estado === EstadoBia::Aprobado ? Carbon::today() : null,
            'fecha_revision' => $estado === EstadoBia::Aprobado ? Carbon::today()->addYear() : null,
        ]);
    }
}
