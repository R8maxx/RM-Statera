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
 * **La pivote se sincroniza sin `detach`.** `PlanificarPrueba` ya creó la fila
 * de cada servicio cubierto; aquí sólo se actualizan `rto_alcanzado_horas` y
 * `rpo_alcanzado_horas` de los que llegan en `$datos['servicios']`, y los que
 * no llegan se quedan como estaban —`syncWithoutDetaching()` no suelta a
 * ninguno—.
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

        return DB::transaction(function () use ($prueba, $datos, $resultado, $actual, $usuario): PruebaContinuidad {
            $prueba->update([
                'estado' => EstadoPrueba::Realizada->value,
                'fecha_realizacion' => $datos['fecha_realizacion'],
                'resultado' => $resultado->value,
                'conclusiones' => $datos['conclusiones'] ?? null,
                'evidencia_id' => $datos['evidencia_id'] ?? null,
            ]);

            $pivote = collect($datos['servicios'] ?? [])->mapWithKeys(
                fn (array $alcanzado, int $servicioId): array => [$servicioId => [
                    'organizacion_id' => $prueba->organizacion_id,
                    'rto_alcanzado_horas' => $alcanzado['rto_alcanzado_horas'] ?? null,
                    'rpo_alcanzado_horas' => $alcanzado['rpo_alcanzado_horas'] ?? null,
                ]],
            )->all();

            if ($pivote !== []) {
                $prueba->servicios()->syncWithoutDetaching($pivote);
            }

            $this->registro->registrar($prueba, $actual, EstadoPrueba::Realizada, $usuario);

            return $prueba->refresh();
        });
    }
}
