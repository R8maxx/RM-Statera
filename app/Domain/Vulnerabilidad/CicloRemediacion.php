<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Domain\Vulnerabilidad\Models\VulnerabilidadTransicion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuánto tardó una vulnerabilidad, contado contra su plazo, y por qué estados pasó.
 *
 * El auditor no pregunta si está cerrada: pregunta cuánto tardó. La ficha lo
 * contesta con dos piezas que salen de aquí, y no del reloj del navegador, para
 * que la barra y la cifra no discrepen.
 *
 * **El plazo corre sólo en abierta y en remediación** (`correPlazo()`): el
 * tramo acaba hoy si sigue corriendo, y si no, el día en que salió de esos dos
 * estados por última vez —a mitigada, aceptada o falso positivo—. Una mitigada
 * ya cumplió aunque le falte la verificación.
 *
 * **El camino es el principal**, abierta → en remediación → mitigada →
 * cerrada, con la fecha de la última vez que se llegó a cada paso. Una aceptada
 * o un falso positivo se sale de él: se pintan los pasos que dio y detrás el
 * estado donde acabó.
 */
final class CicloRemediacion
{
    private const CAMINO = [
        EstadoVulnerabilidad::Abierta,
        EstadoVulnerabilidad::EnRemediacion,
        EstadoVulnerabilidad::Mitigada,
        EstadoVulnerabilidad::Cerrada,
    ];

    /**
     * @param  Collection<int, VulnerabilidadTransicion>  $transiciones  en orden cronológico
     * @return array{dias: int, detectada: string, limite: string, fin: string, finRotulo: string, corre: bool, transcurridos: int, fuera: int}|null
     */
    public function plazo(Vulnerabilidad $vulnerabilidad, Collection $transiciones): ?array
    {
        if ($vulnerabilidad->fecha_limite === null) {
            return null;
        }

        $detectada = $vulnerabilidad->fecha_deteccion->copy()->startOfDay();
        $limite = $vulnerabilidad->fecha_limite->copy()->startOfDay();
        $corre = $vulnerabilidad->estado->correPlazo();
        $salida = $corre ? null : $transiciones
            ->filter(static fn (VulnerabilidadTransicion $t): bool => ($t->estado_anterior?->correPlazo() ?? false)
                && ! $t->estado_nuevo->correPlazo())
            ->last();

        $fin = $salida === null ? Carbon::today() : $salida->created_at->copy()->startOfDay();
        $fin = $fin->lt($detectada) ? $detectada->copy() : $fin;

        return [
            'dias' => (int) $detectada->diffInDays($limite),
            'detectada' => $detectada->toDateString(),
            'limite' => $limite->toDateString(),
            'fin' => $fin->toDateString(),
            'finRotulo' => $salida === null ? ($corre ? 'Hoy' : $vulnerabilidad->estado->etiqueta()) : $salida->estado_nuevo->etiqueta(),
            'corre' => $corre,
            'transcurridos' => (int) $detectada->diffInDays($fin),
            'fuera' => $fin->gt($limite) ? (int) $limite->diffInDays($fin) : 0,
        ];
    }

    /**
     * @param  Collection<int, VulnerabilidadTransicion>  $transiciones  en orden cronológico
     * @return list<array{valor: string, etiqueta: string, tono: string, icono: string, fecha: string|null, paso: string}>
     */
    public function camino(Vulnerabilidad $vulnerabilidad, Collection $transiciones): array
    {
        $actual = $vulnerabilidad->estado;
        $fuera = ! in_array($actual, self::CAMINO, true);

        /*
         * Sólo cuenta lo que pasó desde la última reapertura: un cierre
         * reabierto ya no está cerrado, y su fecha no puede seguir pintada.
         */
        $transiciones = $transiciones->values();
        $reapertura = $transiciones
            ->filter(static fn (VulnerabilidadTransicion $t): bool => $t->estado_nuevo === EstadoVulnerabilidad::Abierta)
            ->keys()
            ->last();
        $vigentes = $reapertura === null ? $transiciones : $transiciones->slice($reapertura);
        $alcanzados = $vigentes->mapWithKeys(static fn (VulnerabilidadTransicion $t): array => [
            $t->estado_nuevo->value => $t->created_at->toIso8601String(),
        ]);

        /* Toda vulnerabilidad nace abierta, aunque su alta no dejara transición. */
        if (! $alcanzados->has(EstadoVulnerabilidad::Abierta->value) && $vulnerabilidad->created_at !== null) {
            $alcanzados->put(EstadoVulnerabilidad::Abierta->value, $vulnerabilidad->created_at->toIso8601String());
        }

        $posicion = $fuera ? -1 : (int) array_search($actual, self::CAMINO, true);
        $pasos = [];

        foreach (self::CAMINO as $indice => $estado) {
            $fecha = $alcanzados->get($estado->value);

            if ($fuera && $fecha === null) {
                continue;
            }

            $paso = match (true) {
                $estado === $actual => 'actual',
                $fecha !== null && ($fuera || $indice < $posicion) => 'dado',
                $indice < $posicion => 'saltado',
                default => 'pendiente',
            };

            $pasos[] = $this->paso($estado, $paso === 'pendiente' ? null : $fecha, $paso);
        }

        if ($fuera) {
            $pasos[] = $this->paso($actual, $alcanzados->get($actual->value), 'actual');
        }

        return $pasos;
    }

    /**
     * `saltado` es un paso del camino que no se dio —de abierta a mitigada sin
     * pasar por remediación—: se dice, en vez de pintarlo como hecho sin fecha.
     *
     * @return array{valor: string, etiqueta: string, tono: string, icono: string, fecha: string|null, paso: string}
     */
    private function paso(EstadoVulnerabilidad $estado, ?string $fecha, string $paso): array
    {
        return [
            'valor' => $estado->value,
            'etiqueta' => $estado->etiqueta(),
            'tono' => $estado->tono(),
            'icono' => $estado->icono(),
            'fecha' => $fecha,
            'paso' => $paso,
        ];
    }
}
