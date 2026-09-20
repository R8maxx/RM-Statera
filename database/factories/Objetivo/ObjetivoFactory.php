<?php

declare(strict_types=1);

namespace Database\Factories\Objetivo;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Objetivos de seguridad sintéticos. Ni uno real de ningún cliente.
 *
 * Nace **propuesto y sin fecha**, que es el único estado que no arrastra nada: los
 * `CHECK` de plazo y de firma sólo muerden en los tres estados comprometidos, y el
 * de cierre acopla la fecha al estado en las dos direcciones.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion` desde el
 * contexto, como en el resto del repositorio. Declararlo aquí lo cortocircuitaría
 * —el trait sólo rellena si viene a nulo— y la fila nacería con un tenant que no
 * es el del contexto, que el `WITH CHECK` de la política rechaza con un error de
 * privilegios que no menciona la palabra «organización». Lo clava
 * `FactoriesSinOrganizacionTest`.
 *
 * @extends Factory<Objetivo>
 */
class ObjetivoFactory extends Factory
{
    protected $model = Objetivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => sprintf('OBJ-%d-%02d', Carbon::today()->year, fake()->unique()->numberBetween(1, 9999)),
            'titulo' => fake()->sentence(),
            'descripcion' => null,
            'recursos' => null,
            'estado' => EstadoObjetivo::Propuesto->value,
            'responsable_id' => null,
            'fecha_objetivo' => null,
            'fecha_cierre' => null,
            'aprobado_por_id' => null,
            'aprobado_en' => null,
            'nota_aprobacion' => null,
        ];
    }

    /**
     * En un estado concreto, con el plazo, la firma y la fecha de cierre que sus
     * `CHECK` exigen.
     *
     * Las tres restricciones acopladas son las que más se olvidan, y por eso las
     * pone la factory y no cada test. Es lo mismo que ya hacen
     * `TareaFactory::enEstado()`, `AuditoriaFactory::cerrada()` y
     * `NoConformidadFactory::enEstado()`.
     *
     * El firmante queda a nulo a propósito cuando no se pasa: `aprobado_en` sin
     * `aprobado_por_id` lo rechaza el `CHECK` de coherencia, así que un test que
     * necesite un objetivo aprobado tiene que decir quién lo firmó.
     */
    public function enEstado(EstadoObjetivo $estado, ?int $firmanteId = null, ?Carbon $fecha = null): self
    {
        return $this->state(function () use ($estado, $firmanteId, $fecha): array {
            $dia = $fecha ?? Carbon::today();

            return [
                'estado' => $estado->value,
                'fecha_objetivo' => $estado->esComprometido() ? $dia->copy()->addMonths(3) : null,
                'fecha_cierre' => $estado->esCerrado() ? $dia : null,
                'aprobado_por_id' => $estado->esComprometido() ? $firmanteId : null,
                'aprobado_en' => $estado->esComprometido() ? $dia : null,
            ];
        });
    }

    /** Aprobado y con la fecha objetivo ya pasada: el único rojo del módulo. */
    public function vencido(int $firmanteId): self
    {
        return $this->enEstado(EstadoObjetivo::Aprobado, $firmanteId)
            ->state(fn (): array => ['fecha_objetivo' => Carbon::today()->subDays(15)]);
    }
}
