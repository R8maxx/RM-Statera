<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\Excepciones\EscalaInvalida;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Una evaluación nueva de un riesgo, que jubila a la anterior.
 *
 * **No modifica la valoración vigente: inserta otra y jubila la que había.** Es lo
 * que convierte «reevaluación periódica» en algo que se puede comparar, y lo que
 * hace que la pregunta «¿qué cambió entre marzo y octubre?» tenga respuesta. Una
 * tabla que se sobrescribiera contestaría siempre «lo de ahora».
 *
 * Y congela dos cosas en el momento de valorar:
 *
 * - **La metodología**, porque sin ella un 12 de marzo es un número sin unidades
 *   en cuanto alguien cambie la escala.
 * - **Las salvaguardas**, porque sin ellas la fila de marzo dirá el mes que viene
 *   que se apoyaba en tres controles implantados, cuando en marzo dos estaban a
 *   medias.
 *
 * El residual que entra es el que haya **declarado una persona**. Aquí no se
 * calcula: `CoberturaSalvaguardas` sugiere y enseña la contradicción cuando la
 * hay, pero quien decide es el propietario del riesgo.
 */
final class ValorarRiesgo
{
    public function __construct(
        private readonly MetodologiaVigente $metodologias,
        private readonly CalculoRiesgo $calculo,
        private readonly CoberturaSalvaguardas $cobertura,
        private readonly ImpactoIntrinseco $impacto,
    ) {}

    /**
     * @param  array{
     *     probabilidad: int,
     *     impacto: int,
     *     probabilidad_residual?: ?int,
     *     impacto_residual?: ?int,
     *     justificacion_residual?: ?string,
     *     decision?: string,
     *     nota?: ?string,
     * }  $datos
     *
     * @throws EscalaInvalida
     */
    public function __invoke(Riesgo $riesgo, array $datos, ?User $autor = null): RiesgoValoracion
    {
        $metodologia = $this->metodologias->para();

        // Se calcula ANTES de abrir la transacción: si la escala no admite estos
        // valores, no hay nada que jubilar ni que deshacer.
        $intrinseco = $this->calculo->producto($metodologia, $datos['probabilidad'], $datos['impacto']);

        /*
         * Los tres campos del residual viajan juntos o no viaja ninguno. Medio
         * residual —probabilidad sí, impacto no— no es medio dato: es un dato que
         * no se puede multiplicar, y guardarlo a medias deja una fila que parece
         * valorada y no lo está.
         */
        [$probabilidadResidual, $impactoResidual, $residual] = $this->residual($metodologia, $datos);

        $riesgo->loadMissing(['activos', 'salvaguardas.requisito']);

        return DB::transaction(function () use (
            $riesgo,
            $datos,
            $autor,
            $metodologia,
            $intrinseco,
            $probabilidadResidual,
            $impactoResidual,
            $residual,
        ): RiesgoValoracion {
            /*
             * Jubilar primero, por el índice único parcial: sólo cabe una vigente
             * por riesgo, así que insertar antes de jubilar choca con él. Y va por
             * el query builder y no por el modelo porque el trigger de
             * inmutabilidad deja pasar exactamente este cambio y ningún otro.
             */
            RiesgoValoracion::query()
                ->where('riesgo_id', $riesgo->id)
                ->where('vigente', true)
                ->update(['vigente' => false]);

            return RiesgoValoracion::query()->create([
                'riesgo_id' => $riesgo->id,
                'vigente' => true,
                'probabilidad' => $datos['probabilidad'],
                'impacto' => $datos['impacto'],
                'impacto_por_dimension' => $this->impacto->porDimension($riesgo, $metodologia),
                'riesgo_intrinseco' => $intrinseco,
                'probabilidad_residual' => $probabilidadResidual,
                'impacto_residual' => $impactoResidual,
                'riesgo_residual' => $residual,
                'justificacion_residual' => $residual === null ? null : ($datos['justificacion_residual'] ?? null),
                'decision' => $datos['decision'] ?? DecisionRiesgo::Mitigar->value,
                'escala' => $metodologia->aArray(),
                'salvaguardas' => $this->cobertura->instantanea($riesgo),
                'valorada_por_id' => $autor?->id,
                'valorada_en' => Carbon::today(),
                'nota' => $datos['nota'] ?? null,
            ])->refresh();
        });
    }

    /**
     * Los tres campos del residual, o los tres a nulo.
     *
     * @param  array<string, mixed>  $datos
     * @return array{?int, ?int, ?int}
     *
     * @throws EscalaInvalida
     */
    private function residual(Metodologia $metodologia, array $datos): array
    {
        $probabilidad = $datos['probabilidad_residual'] ?? null;
        $impacto = $datos['impacto_residual'] ?? null;

        if (! is_int($probabilidad) || ! is_int($impacto)) {
            return [null, null, null];
        }

        return [$probabilidad, $impacto, $this->calculo->producto($metodologia, $probabilidad, $impacto)];
    }
}
