<?php

declare(strict_types=1);

namespace Database\Factories\Incidente;

use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\PlazoNotificacion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Incidentes sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **abierto, sin notificaciones y detectado hace un rato**, que es el estado
 * que no arrastra ningún `CHECK`: el de cierre exige fecha y lección, y los dos de
 * notificación sólo muerden si está marcada como notificable.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto. Lo clava `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Incidente>
 */
class IncidenteFactory extends Factory
{
    protected $model = Incidente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('INC-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 99)),
            'titulo' => fake()->sentence(5),
            'descripcion' => fake()->paragraph(),
            'sistema_id' => null,
            'clasificacion' => ClasificacionIncidente::Otros->value,
            'peligrosidad' => PeligrosidadIncidente::Baja->value,
            'fecha_deteccion' => Carbon::now()->subHours(2),
            'fecha_inicio' => null,
            'afecta_confidencialidad' => false,
            'afecta_integridad' => false,
            'afecta_disponibilidad' => false,
            'afecta_autenticidad' => false,
            'afecta_trazabilidad' => false,
            'impacto' => null,
            'acciones_contencion' => null,
            'leccion_aprendida' => null,
            'estado' => EstadoIncidente::Abierto->value,
            'responsable_id' => null,
            'fecha_cierre' => null,
            'notificable_aepd' => false,
            'notificado_aepd_en' => null,
            'notificable_ccn_cert' => false,
            'notificado_ccn_cert_en' => null,
        ];
    }

    /**
     * En un estado concreto, con la lección y la fecha que sus `CHECK` exigen.
     *
     * Las dos restricciones acopladas son las que más se olvidan, y por eso las
     * pone la factory y no cada test. Es lo mismo que ya hacen
     * `TareaFactory::enEstado()` y `ObjetivoFactory::enEstado()`.
     */
    public function enEstado(EstadoIncidente $estado): static
    {
        return $this->state(fn (): array => [
            'estado' => $estado->value,
            'fecha_cierre' => $estado->esCerrado() ? Carbon::now() : null,
            'leccion_aprendida' => $estado->esCerrado()
                ? 'Se revisaron las reglas del filtro y se añadió el dominio a la lista de bloqueo.'
                : null,
        ]);
    }

    /**
     * Notificable a la AEPD y **con el plazo vencido**: el único rojo del módulo.
     *
     * Las horas salen de `PlazoNotificacion::HORAS_AEPD` y no de un 73 escrito a
     * mano: si algún día la ley cambiara el plazo, un literal dejaría en verde un
     * test que prueba justo lo contrario.
     */
    public function fueraDePlazoAepd(): static
    {
        return $this->state(fn (): array => [
            'afecta_confidencialidad' => true,
            'notificable_aepd' => true,
            'notificado_aepd_en' => null,
            'fecha_deteccion' => Carbon::now()->subHours(PlazoNotificacion::HORAS_AEPD + 1),
        ]);
    }

    /** Notificable y dentro de plazo: trabajo urgente, no incumplimiento. */
    public function enPlazoAepd(): static
    {
        return $this->state(fn (): array => [
            'afecta_confidencialidad' => true,
            'notificable_aepd' => true,
            'notificado_aepd_en' => null,
            'fecha_deteccion' => Carbon::now()->subHours(1),
        ]);
    }

    public function notificadoAepd(): static
    {
        return $this->state(fn (): array => [
            'afecta_confidencialidad' => true,
            'notificable_aepd' => true,
            'fecha_deteccion' => Carbon::now()->subHours(4),
            'notificado_aepd_en' => Carbon::now()->subHours(1),
        ]);
    }
}
