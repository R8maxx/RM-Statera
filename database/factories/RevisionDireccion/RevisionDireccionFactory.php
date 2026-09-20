<?php

declare(strict_types=1);

namespace Database\Factories\RevisionDireccion;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Revisiones por la dirección sintéticas. Ni una real de ninguna organización.
 *
 * Nace **planificada**, que es el único estado que no arrastra nada: sin firma y
 * sin instantánea, que el `CHECK` acopla a `aprobada`.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<RevisionDireccion>
 */
class RevisionDireccionFactory extends Factory
{
    protected $model = RevisionDireccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasta = Carbon::today();

        return [
            'codigo' => sprintf('RD-%d-%02d', $hasta->year, fake()->unique()->numberBetween(1, 9999)),
            'fecha' => $hasta,
            'periodo_desde' => $hasta->copy()->subYear(),
            'periodo_hasta' => $hasta,
            'asistentes' => null,
            'estado' => EstadoRevision::Planificada->value,
            'instantanea' => null,
            'conclusiones' => null,
            'aprobada_por_id' => null,
            'aprobada_en' => null,
        ];
    }

    public function enCurso(): self
    {
        return $this->state(fn (): array => ['estado' => EstadoRevision::EnCurso->value]);
    }

    /**
     * Aprobada, con la firma y una instantánea mínima que el `CHECK` exige.
     *
     * **La instantánea no se deja vacía**: el `CHECK` sólo mira que no sea nula, y
     * un array vacío pasaría — pero un acta con la instantánea vacía no es un caso
     * que pueda existir por los caminos normales, así que una factory que lo
     * produjera dejaría pasar tests sobre un estado imposible. Quien necesite las
     * siete entradas de verdad tiene que pasar por `AprobarRevision`, que es el
     * único sitio que las recoge.
     */
    public function aprobada(int $firmanteId, ?Carbon $fecha = null): self
    {
        return $this->state(fn (): array => [
            'estado' => EstadoRevision::Aprobada->value,
            'aprobada_por_id' => $firmanteId,
            'aprobada_en' => $fecha ?? Carbon::now(),
            'instantanea' => ['recogidasEn' => ($fecha ?? Carbon::now())->toIso8601String()],
        ]);
    }

    /** En un periodo concreto, que es de lo que habla el acta. */
    public function delPeriodo(Carbon $desde, Carbon $hasta): self
    {
        return $this->state(fn (): array => [
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'fecha' => $hasta,
        ]);
    }
}
