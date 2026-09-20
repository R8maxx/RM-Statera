<?php

declare(strict_types=1);

namespace Database\Factories\NoConformidad;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * No conformidades sintéticas. Ni una real de ningún cliente.
 *
 * Nace **abierta y de detección propia**, que es el único estado que no arrastra
 * nada: sin hallazgo detrás —el `CHECK` sólo admite hallazgo con origen
 * `auditoria`— y sin fechas de cierre ni de verificación, que los dos `CHECK` de
 * coherencia acoplan al estado.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto, como en el resto del repositorio.
 *
 * @extends Factory<NoConformidad>
 */
class NoConformidadFactory extends Factory
{
    protected $model = NoConformidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('NC-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 9999)),
            'origen' => OrigenNoConformidad::Propia->value,
            'hallazgo_id' => null,
            'incidente_id' => null,
            'descripcion' => fake()->sentence(),
            'correccion_inmediata' => null,
            'analisis_causa_raiz' => null,
            'estado' => EstadoNoConformidad::Abierta->value,
            'responsable_id' => null,
            'fecha_deteccion' => Carbon::today(),
            'fecha_prevista' => null,
            'fecha_cierre' => null,
            'fecha_verificacion' => null,
            'verificada_por_id' => null,
            'resultado_verificacion' => null,
        ];
    }

    /** De un hallazgo, que es el único origen que admite tenerlo. */
    public function deHallazgo(int $hallazgoId): self
    {
        return $this->state(fn (): array => [
            'origen' => OrigenNoConformidad::Auditoria->value,
            'hallazgo_id' => $hallazgoId,
            // Un `CHECK` impide que vengan de un hallazgo y de un incidente a la
            // vez, así que el estado limpia el otro extremo.
            'incidente_id' => null,
        ]);
    }

    /** De un incidente (§ 4.10), espejo exacto del anterior. */
    public function deIncidente(int $incidenteId): self
    {
        return $this->state(fn (): array => [
            'origen' => OrigenNoConformidad::Incidente->value,
            'incidente_id' => $incidenteId,
            'hallazgo_id' => null,
        ]);
    }

    /**
     * En un estado concreto, con las fechas que su `CHECK` exige.
     *
     * Las dos restricciones acopladas son las que más se olvidan —cerrada sin
     * fecha de cierre no se puede insertar, verificada sin fecha de verificación
     * tampoco—, y por eso las pone la factory y no cada test. Es lo mismo que ya
     * hacen `TareaFactory::enEstado()` y `AuditoriaFactory::cerrada()`.
     */
    public function enEstado(EstadoNoConformidad $estado, ?Carbon $fecha = null): self
    {
        return $this->state(function () use ($estado, $fecha): array {
            $dia = $fecha ?? Carbon::today();

            return [
                'estado' => $estado->value,
                'fecha_cierre' => $estado->esCerrada() ? $dia : null,
                'fecha_verificacion' => $estado->exigeVerificacion() ? $dia : null,
            ];
        });
    }

    public function vencida(): self
    {
        return $this->state(fn (): array => ['fecha_prevista' => Carbon::today()->subDays(15)]);
    }
}
