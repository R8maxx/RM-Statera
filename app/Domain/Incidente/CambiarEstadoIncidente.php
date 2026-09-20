<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Excepciones\TransicionDeIncidenteNoPermitida;
use App\Domain\Incidente\Models\Incidente;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de un incidente, con su histórico y su fecha de cierre.
 *
 * **Cerrar exige lección aprendida, y la regla vive aquí.** `op.exp.7` pide
 * aprender del incidente, y es el paso que todo el mundo se salta el día que el
 * servicio vuelve. Lo impone además `incidentes_leccion_check`, y esta guarda
 * existe para que el mensaje sea legible y no el nombre de una restricción —el
 * mismo razonamiento que la guarda del cierre de una auditoría—. Y está en el
 * dominio y no sólo en el `FormRequest` porque la regla vale igual para un
 * importador.
 *
 * **La fecha la pone el dominio y nunca el formulario**, igual que en tareas, no
 * conformidades, objetivos y mejoras: la base acopla `estado` con `fecha_cierre`
 * en las dos direcciones.
 *
 * **Reabrir exige motivo escrito.** Volver a `resuelto` desde `cerrado`, o a
 * `en_tratamiento` desde `resuelto`, es decir que lo que alguien dio por hecho no
 * lo estaba, y «qué falló» es lo único que explica el ir y venir. Es la misma
 * regla que ya tienen la no conformidad que vuelve a tratamiento y el objetivo
 * que se reabre.
 */
final class CambiarEstadoIncidente
{
    public function __construct(private readonly RegistroTransicionesIncidente $registro) {}

    public function __invoke(
        Incidente $incidente,
        EstadoIncidente $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): Incidente {
        $actual = $incidente->estado;

        if ($actual === $nuevo) {
            return $incidente;
        }

        if (! $actual->admite($nuevo)) {
            throw TransicionDeIncidenteNoPermitida::entre($actual, $nuevo);
        }

        $this->exigirLeccion($incidente, $nuevo);
        $this->exigirMotivo($actual, $nuevo, $nota);

        return DB::transaction(function () use ($incidente, $actual, $nuevo, $usuario, $nota): Incidente {
            $incidente->update([
                'estado' => $nuevo->value,
                'fecha_cierre' => $nuevo->esCerrado() ? Carbon::now() : null,
            ]);

            $this->registro->registrar($incidente, $actual, $nuevo, $usuario, $nota);

            return $incidente->refresh();
        });
    }

    /**
     * @throws TransicionDeIncidenteNoPermitida
     */
    private function exigirLeccion(Incidente $incidente, EstadoIncidente $nuevo): void
    {
        if (! $nuevo->esCerrado()) {
            return;
        }

        if (trim((string) $incidente->leccion_aprendida) === '') {
            throw TransicionDeIncidenteNoPermitida::sinLeccion();
        }
    }

    /**
     * **Volver atrás exige decir por qué; avanzar no.**
     *
     * Pedir un texto para pasar de abierto a en tratamiento convertiría en
     * trámite el gesto que más se repite mientras se apaga el fuego. Lo que sí
     * necesita explicación es deshacer lo que alguien dio por terminado.
     *
     * @throws TransicionDeIncidenteNoPermitida
     */
    private function exigirMotivo(EstadoIncidente $actual, EstadoIncidente $nuevo, ?string $nota): void
    {
        if ($nota !== null && trim($nota) !== '') {
            return;
        }

        $retrocede = match ([$actual, $nuevo]) {
            [EstadoIncidente::Cerrado, EstadoIncidente::Resuelto],
            [EstadoIncidente::Resuelto, EstadoIncidente::EnTratamiento],
            [EstadoIncidente::EnTratamiento, EstadoIncidente::Abierto] => true,
            default => false,
        };

        if ($retrocede) {
            throw TransicionDeIncidenteNoPermitida::sinMotivo($nuevo);
        }
    }
}
