<?php

declare(strict_types=1);

namespace Database\Factories\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\CompromisoCumplimiento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Cumplimientos sintéticos.
 *
 * `cubre_hasta` sale de `fecha` más doce meses, que es la cadencia por defecto de
 * `CompromisoFactory`: el `CHECK` de la tabla exige que sea posterior a `fecha`,
 * así que un valor nulo o igual no se puede insertar. Si el test usa otra
 * cadencia, `deCompromiso()` la resuelve leyendo la del compromiso, que es lo que
 * hace `RegistrarCumplimiento` de verdad.
 *
 * `organizacion_id` no se declara: lo rellena `PerteneceAOrganizacion`.
 *
 * @extends Factory<CompromisoCumplimiento>
 */
class CompromisoCumplimientoFactory extends Factory
{
    protected $model = CompromisoCumplimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = Carbon::today();

        return [
            'fecha' => $fecha,
            'cubre_hasta' => $fecha->copy()->addYear(),
            'auditoria_id' => null,
            'revision_direccion_id' => null,
            'documento_id' => null,
            'evidencia_id' => null,
            'nota' => null,
            'registrado_por_id' => null,
        ];
    }

    /** Con la cobertura que le toca según la cadencia del compromiso. */
    public function deCompromiso(Compromiso $compromiso, ?Carbon $fecha = null): self
    {
        $fecha ??= Carbon::today();

        return $this->state(fn (): array => [
            'compromiso_id' => $compromiso->id,
            'fecha' => $fecha,
            'cubre_hasta' => $compromiso->cadencia()->despuesDe($fecha),
        ]);
    }
}
