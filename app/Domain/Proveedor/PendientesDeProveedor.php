<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Proveedor\Enums\ResultadoClausula;
use App\Domain\Proveedor\Enums\ResultadoEvaluacion;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorCertificacion;
use App\Domain\Proveedor\Models\ProveedorEvaluacionClausula;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;

/**
 * Lo que falta para homologar a un proveedor, o para que siga homologado.
 *
 * Es la respuesta a «¿qué hay que hacer con él?», y la ficha la pone arriba:
 * antes estaba repartida en cuatro tarjetas, con la condición de la evaluación
 * dentro de un plegable y su tarea en otra tarjeta, sin nada que las uniera.
 *
 * Cinco cosas, en el orden en que se enseñan:
 *
 * - **La primera evaluación**, si no la hay.
 * - **La reevaluación vencida** y **los certificados caducados**, los dos rojos
 *   de `RegistroProveedores::alertas()`, con la misma regla: se leen de los
 *   mismos scopes y métodos, no de una condición escrita otra vez.
 * - **La condición de la última evaluación**, si no fue apta sin más: sus
 *   conclusiones, las cláusulas que no se cumplen y las tareas abiertas del
 *   proveedor, que son lo que se hace para levantarla.
 * - **Las contradicciones entre la ficha y la última evaluación**, que salen de
 *   las cláusulas que el catálogo marca con `dato_de_ficha`.
 *
 * Lo retirado no tiene nada pendiente: ya no se trabaja con él.
 */
final readonly class PendientesDeProveedor
{
    /**
     * @return array{
     *     sinEvaluar: bool,
     *     reevaluacionVencida: ?string,
     *     certificacionesCaducadas: list<array{id: int, etiqueta: string, caducaEn: ?string}>,
     *     condicion: ?array{fecha: string, resultado: string, tono: string, icono: string, conclusiones: ?string, incumplidas: list<array{codigo: string, titulo: string, nota: ?string}>},
     *     tareasAbiertas: list<array{id: int, titulo: string, estado: string, tono: string, icono: string, responsable: ?string, plazo: array{fecha: ?string, etiqueta: string, tono: string}}>,
     *     contradicciones: list<array{codigo: string, titulo: string, clave: string, dato: string, valor: string, resultado: string, motivo: string, sugerencia: string}>,
     *     total: int
     * }
     */
    public function de(Proveedor $proveedor): array
    {
        if (! $proveedor->estado->seReevalua()) {
            return [
                'sinEvaluar' => false,
                'reevaluacionVencida' => null,
                'certificacionesCaducadas' => [],
                'condicion' => null,
                'tareasAbiertas' => [],
                'contradicciones' => [],
                'total' => 0,
            ];
        }

        $ultima = $proveedor->ultimaEvaluacion()->with('clausulas.clausula')->first();

        $vencida = Proveedor::query()->whereKey($proveedor->id)->reevaluacionVencida()->exists();

        $caducadas = $proveedor->certificaciones()
            ->caducadas()
            ->orderBy('caduca_en')
            ->get()
            ->map(static fn (ProveedorCertificacion $certificacion): array => [
                'id' => $certificacion->id,
                'etiqueta' => $certificacion->etiqueta(),
                'caducaEn' => $certificacion->caduca_en?->toDateString(),
            ])->values()->all();

        $condicion = $ultima === null || $ultima->resultado === ResultadoEvaluacion::Apto ? null : [
            'fecha' => $ultima->fecha->toDateString(),
            'resultado' => $ultima->resultado->etiqueta(),
            'tono' => $ultima->resultado->tono(),
            'icono' => $ultima->resultado->icono(),
            'conclusiones' => $ultima->conclusiones,
            'incumplidas' => $ultima->clausulas
                ->filter(static fn (ProveedorEvaluacionClausula $una): bool => $una->resultado === ResultadoClausula::NoCumple)
                ->sortBy(static fn (ProveedorEvaluacionClausula $una): string => (string) $una->clausula?->codigo)
                ->map(static fn (ProveedorEvaluacionClausula $una): array => [
                    'codigo' => (string) $una->clausula?->codigo,
                    'titulo' => (string) $una->clausula?->titulo,
                    'nota' => $una->nota,
                ])->values()->all(),
        ];

        $tareas = $proveedor->tareas()
            ->abiertas()
            ->with('responsable:id,name')
            ->orderByRaw('fecha_limite IS NULL')
            ->orderBy('fecha_limite')
            ->get()
            ->map(static function (Tarea $tarea): array {
                $plazo = Plazo::de($tarea);

                return [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'icono' => $tarea->estado->icono(),
                    'responsable' => $tarea->responsable?->name,
                    'plazo' => ['fecha' => $plazo->fecha, 'etiqueta' => $plazo->etiqueta, 'tono' => $plazo->tono],
                ];
            })->values()->all();

        $contradicciones = [];

        foreach ($ultima->clausulas ?? [] as $una) {
            $dato = $una->clausula?->dato_de_ficha;
            $contradiccion = $dato?->contradiccion($proveedor, $una->resultado);

            if ($dato === null || $contradiccion === null) {
                continue;
            }

            $contradicciones[] = [
                'codigo' => (string) $una->clausula->codigo,
                'titulo' => (string) $una->clausula->titulo,
                'clave' => $dato->value,
                'dato' => $dato->etiqueta(),
                'valor' => $dato->valorEn($proveedor),
                'resultado' => $una->resultado->etiqueta(),
                'motivo' => $contradiccion['motivo'],
                'sugerencia' => $contradiccion['sugerencia'],
            ];
        }

        usort($contradicciones, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);

        $sinEvaluar = $ultima === null;

        return [
            'sinEvaluar' => $sinEvaluar,
            'reevaluacionVencida' => $vencida ? $proveedor->proxima_evaluacion?->toDateString() : null,
            'certificacionesCaducadas' => $caducadas,
            'condicion' => $condicion,
            'tareasAbiertas' => $tareas,
            'contradicciones' => $contradicciones,
            // Lo que se cuenta como pendiente es cada fila del bloque: las
            // tareas van dentro de la condición, y todas las contradicciones
            // son una sola fila, «revisar la ficha».
            'total' => (int) $sinEvaluar
                + (int) $vencida
                + count($caducadas)
                + (int) ($condicion !== null)
                + (int) ($contradicciones !== []),
        ];
    }
}
