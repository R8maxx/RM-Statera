<?php

declare(strict_types=1);

namespace App\Domain\Tarea;

use App\Domain\Tarea\Models\Subtarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la lista de comprobación entera de una tarea.
 *
 * **Una sola operación para todo.** Añadir, renombrar, marcar, reordenar y
 * borrar son la misma llamada, porque en una lista de comprobación se hacen
 * juntas: quien reordena suele estar además renombrando algo. Con una ruta por
 * gesto harían falta cinco `FormRequest`, cinco acciones y un cliente que las
 * orqueste, y reordenar seguiría siendo un problema aparte.
 *
 * El orden llega implícito en la posición del array: lo que el cliente manda
 * primero va primero. Así reordenar no necesita ni un campo ni un gesto propio.
 *
 * Lo que **no** hace: cerrar la tarea cuando se marcan todos los pasos. Cerrarla
 * es una decisión con su transición, su fecha y su autor, y deducirla de una
 * casilla dejaría el histórico contando algo que nadie decidió.
 */
final class GuardarSubtareas
{
    /**
     * @param  list<array{id?: int|null, titulo: string, hecha: bool}>  $pasos
     * @return list<Subtarea>
     */
    public function __invoke(Tarea $tarea, array $pasos): array
    {
        return DB::transaction(function () use ($tarea, $pasos): array {
            $existentes = $tarea->subtareas()->get()->keyBy('id');
            $conservados = [];

            foreach ($pasos as $orden => $paso) {
                $id = $paso['id'] ?? null;
                $subtarea = $id === null ? null : $existentes->get($id);

                // Un id que no es de esta tarea se trata como un paso nuevo, no
                // como un error: lo que llega del cliente no manda sobre a quién
                // pertenece una fila.
                $subtarea ??= new Subtarea(['tarea_id' => $tarea->id]);

                $subtarea->fill([
                    'tarea_id' => $tarea->id,
                    'titulo' => trim($paso['titulo']),
                    'orden' => $orden,
                    // Se sella al marcar y se limpia al desmarcar, pero **no se
                    // vuelve a sellar** si ya estaba marcado: la fecha es cuándo
                    // se hizo, no cuándo se guardó la lista por última vez.
                    'hecha_en' => $paso['hecha']
                        ? ($subtarea->hecha_en ?? Carbon::now())
                        : null,
                ]);

                $subtarea->save();

                $conservados[] = $subtarea->id;
            }

            // Lo que no venía en la lista se ha quitado.
            $tarea->subtareas()->whereNotIn('id', $conservados === [] ? [0] : $conservados)->delete();

            return $tarea->subtareas()->get()->all();
        });
    }
}
