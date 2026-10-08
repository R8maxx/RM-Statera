<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Copia\EstadoDeLasCopias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * La salud del servicio, para quien administra la plataforma (punto 55): las
 * copias, las colas y los trabajos que fallaron.
 *
 * **De un trabajo fallido se enseña la cola, la clase y la primera línea del
 * error, y nunca el payload**: lleva los datos del job, que pueden ser correos
 * o nombres de un cliente.
 */
final class SaludDelServicio
{
    /** Las colas de Horizon que se vigilan. */
    public const COLAS = ['documentos', 'importadores', 'notificaciones', 'default'];

    public function __construct(private readonly EstadoDeLasCopias $copias) {}

    /**
     * @return array{copias: array<string, mixed>, colas: list<array{nombre: string, pendientes: ?int}>, fallidos: list<array{id: int, cola: string, trabajo: string, error: string, fecha: string}>, totalFallidos: int}
     */
    public function resumen(): array
    {
        return [
            'copias' => $this->copias->resumen(),
            'colas' => array_map(fn (string $cola): array => ['nombre' => $cola, 'pendientes' => $this->pendientes($cola)], self::COLAS),
            'fallidos' => $this->fallidos(),
            'totalFallidos' => DB::table('failed_jobs')->count(),
        ];
    }

    private function pendientes(string $cola): ?int
    {
        try {
            return Queue::size($cola);
        } catch (Throwable) {
            return null;
        }
    }

    /** «Illuminate\\Database\\QueryException: SQLSTATE…» → «QueryException». */
    private static function claseDelError(string $excepcion): string
    {
        $primera = (string) strtok($excepcion, "\n");
        $clase = trim((string) strtok($primera, ':( '));

        return class_basename($clase !== '' ? $clase : 'Error');
    }

    /**
     * @return list<array{id: int, cola: string, trabajo: string, error: string, fecha: string}>
     */
    private function fallidos(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get(['id', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(static function (object $fila): array {
                /** @var array{displayName?: string} $payload */
                $payload = json_decode((string) $fila->payload, true) ?: [];

                return [
                    'id' => (int) $fila->id,
                    'cola' => (string) $fila->queue,
                    // Sólo el nombre del trabajo: el resto del payload son sus datos.
                    'trabajo' => class_basename((string) ($payload['displayName'] ?? 'desconocido')),
                    // Sólo la clase de la excepción: su mensaje puede llevar una
                    // consulta con sus valores o un correo. El detalle, en Horizon.
                    'error' => self::claseDelError((string) $fila->exception),
                    'fecha' => (string) $fila->failed_at,
                ];
            })
            ->values()
            ->all();
    }
}
