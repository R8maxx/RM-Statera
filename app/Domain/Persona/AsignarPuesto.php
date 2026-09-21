<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Models\AsignacionPuesto;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\Models\Puesto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quién ocupa qué puesto, con vigencia y sin borrar nada.
 *
 * Es el hermano de `DesignarRol`: misma forma —asignar, cerrar, nunca borrar— y
 * mismo motivo, que es el invariante 7. Lo que cambia es la regla: aquí no hay
 * incompatibilidad que mirar, hay **exclusividad**. Una persona ocupa un puesto a
 * la vez, así que asignarle uno nuevo **cierra el anterior** en vez de chocar con
 * el índice único parcial.
 *
 * Cerrar y abrir van en la misma transacción: entre las dos escrituras la persona
 * estaría sin puesto, y si la segunda fallara se quedaría así.
 */
final class AsignarPuesto
{
    /**
     * @throws PersonaNoDesignable
     */
    public function __invoke(
        Persona $persona,
        Puesto $puesto,
        ?Carbon $desde = null,
        ?User $asignadaPor = null,
        ?string $nota = null,
    ): AsignacionPuesto {
        // Mismo criterio que un nombramiento: a quien ya no está no se le asigna
        // un puesto. Lo que sí se puede es consultar el que tuvo.
        if (! $persona->estaActiva()) {
            throw PersonaNoDesignable::porEstarDeBaja($persona->nombre);
        }

        $inicio = $desde ?? Carbon::today();

        return DB::transaction(function () use ($persona, $puesto, $inicio, $asignadaPor, $nota): AsignacionPuesto {
            $this->cerrarVigente($persona, $inicio);

            return AsignacionPuesto::query()->create([
                'organizacion_id' => $persona->organizacion_id,
                'persona_id' => $persona->id,
                'puesto_id' => $puesto->id,
                'desde' => $inicio,
                'asignada_por_id' => $asignadaPor?->id,
                'nota' => $nota,
            ]);
        });
    }

    /**
     * Deja el puesto sin sustituirlo por otro: la persona se queda sin asignación
     * vigente, que es lo que pasa de verdad cuando alguien sale de un puesto y
     * todavía no se sabe a cuál va.
     */
    public function cerrar(AsignacionPuesto $asignacion, ?Carbon $hasta = null): AsignacionPuesto
    {
        $fin = $hasta ?? Carbon::today();

        // Nunca antes de empezar: el `CHECK` lo rechaza y su error hablaría de
        // una restricción en vez de decir que las fechas están al revés.
        if ($fin->isBefore($asignacion->desde)) {
            $fin = $asignacion->desde;
        }

        $asignacion->update(['hasta' => $fin]);

        return $asignacion->refresh();
    }

    /**
     * Cierra la asignación vigente, si la hay, el día antes de empezar la nueva.
     *
     * **El día antes y no el mismo**: dos asignaciones que se solapan un día
     * harían que «qué puesto ocupaba el 3 de marzo» tuviera dos respuestas. Y si
     * la nueva empieza el mismo día que empezó la vieja —una corrección, no un
     * cambio—, se cierra en su propia fecha de inicio, que el `CHECK` sí admite.
     */
    private function cerrarVigente(Persona $persona, Carbon $inicio): void
    {
        $vigente = AsignacionPuesto::query()
            ->where('persona_id', $persona->id)
            ->whereNull('hasta')
            ->first();

        if ($vigente === null) {
            return;
        }

        $fin = $inicio->copy()->subDay();

        $this->cerrar($vigente, $fin->isBefore($vigente->desde) ? $vigente->desde : $fin);
    }
}
