<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sella el resultado de una prueba de continuidad: fecha, resultado y, por
 * servicio, lo que de verdad se alcanzó.
 *
 * **Sólo desde `planificada`.** No hay un `CambiarEstadoPrueba` genérico —ver
 * la cabecera de `EstadoPrueba`—, así que esta es la única puerta hacia
 * `realizada`, y exige el estado de origen antes de tocar nada.
 *
 * **La pivote sólo se actualiza, nunca se amplía.** `PlanificarPrueba` ya creó
 * la fila de cada servicio cubierto; aquí se actualizan `rto_alcanzado_horas` y
 * `rpo_alcanzado_horas` de los que llegan en `$datos['servicios']` con
 * `updateExistingPivot()`, que no inserta nada. Un `activo_id` que la prueba
 * no cubre es un dato que no encaja —no un servicio nuevo que se cuela de
 * paso— y se rechaza antes de tocar nada con
 * `TransicionDePruebaNoPermitida::servicioAjeno()`.
 */
final class RegistrarResultadoPrueba
{
    public function __construct(private readonly RegistroTransicionesPrueba $registro) {}

    /**
     * @param  array{
     *     fecha_realizacion: mixed,
     *     resultado: ResultadoPrueba|string,
     *     conclusiones?: ?string,
     *     evidencia_id?: ?int,
     *     servicios?: array<int, array{rto_alcanzado_horas?: ?int, rpo_alcanzado_horas?: ?int}>,
     * }  $datos
     *
     * @throws TransicionDePruebaNoPermitida
     */
    public function __invoke(PruebaContinuidad $prueba, array $datos, ?User $usuario = null): PruebaContinuidad
    {
        $actual = $prueba->estado;

        if ($actual !== EstadoPrueba::Planificada) {
            throw TransicionDePruebaNoPermitida::entre($actual, EstadoPrueba::Realizada);
        }

        $resultado = $datos['resultado'] instanceof ResultadoPrueba
            ? $datos['resultado']
            : ResultadoPrueba::from($datos['resultado']);

        $servicios = $datos['servicios'] ?? [];

        $this->exigirServiciosCubiertos($prueba, $servicios);

        return DB::transaction(function () use ($prueba, $datos, $resultado, $servicios, $actual, $usuario): PruebaContinuidad {
            $prueba->update([
                'estado' => EstadoPrueba::Realizada->value,
                'fecha_realizacion' => $datos['fecha_realizacion'],
                'resultado' => $resultado->value,
                'conclusiones' => $datos['conclusiones'] ?? null,
                'evidencia_id' => $datos['evidencia_id'] ?? null,
            ]);

            foreach ($servicios as $servicioId => $alcanzado) {
                $prueba->servicios()->updateExistingPivot((int) $servicioId, [
                    'rto_alcanzado_horas' => $alcanzado['rto_alcanzado_horas'] ?? null,
                    'rpo_alcanzado_horas' => $alcanzado['rpo_alcanzado_horas'] ?? null,
                ]);
            }

            $this->registro->registrar($prueba, $actual, EstadoPrueba::Realizada, $usuario);

            return $prueba->refresh();
        });
    }

    /**
     * @param  array<int, array{rto_alcanzado_horas?: ?int, rpo_alcanzado_horas?: ?int}>  $servicios
     *
     * @throws TransicionDePruebaNoPermitida
     */
    private function exigirServiciosCubiertos(PruebaContinuidad $prueba, array $servicios): void
    {
        if ($servicios === []) {
            return;
        }

        $cubiertos = $prueba->servicios()->pluck('activos.id')->all();

        foreach (array_keys($servicios) as $servicioId) {
            if (! in_array((int) $servicioId, $cubiertos, true)) {
                throw TransicionDePruebaNoPermitida::servicioAjeno((int) $servicioId);
            }
        }
    }
}
