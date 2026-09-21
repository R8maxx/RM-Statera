<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\Models\PasoPersona;
use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarda entera una de las dos checklists de una persona.
 *
 * Calcado de cómo se guardan las subtareas de una tarea, y por lo mismo: añadir,
 * renombrar, marcar, reordenar y borrar van juntos en una lista de comprobación, y
 * **el orden llega implícito en la posición del array**, así que reordenar no
 * necesita ni campo ni gesto propio.
 *
 * Tres reglas que se heredan de allí y que aquí valen igual:
 *
 * 1. **`hecho_en` no se vuelve a sellar** si el paso ya estaba marcado: la fecha es
 *    cuándo se hizo el paso, no cuándo se guardó la lista.
 * 2. **Lo que no viene en la lista se borra**, que es lo que permite quitar un paso
 *    sin un gesto aparte.
 * 3. **Un `id` que no es de esta persona y de este tipo se trata como un paso
 *    nuevo**: lo que llega del cliente no manda sobre a quién pertenece una fila.
 *
 * Y una que es de aquí: **las dos listas se guardan por separado**. La de alta y
 * la de baja se rellenan con meses de diferencia y por gente distinta; una sola
 * ruta para las dos haría que guardar la de salida borrara la de entrada si el
 * cliente se dejara un campo.
 *
 * @phpstan-type PasoEntrante array{id?: int|string|null, titulo?: string|null, hecho?: bool|null}
 */
final class GuardarPasos
{
    /**
     * El tope de pasos de una checklist.
     *
     * Público porque lo leen las otras dos capas: el `FormRequest`, que es
     * quien lo rechaza con un mensaje legible, y la ficha, que lo enseña. Con
     * el número escrito tres veces, el 51.º paso se perdía contra un 422 que
     * la pantalla no pintaba en ningún sitio.
     */
    public const TOPE = 50;

    /**
     * @param  list<PasoEntrante>  $pasos
     */
    public function __invoke(Persona $persona, TipoPasoPersona $tipo, array $pasos): void
    {
        DB::transaction(function () use ($persona, $tipo, $pasos): void {
            /** @var array<int, PasoPersona> $existentes */
            $existentes = $persona->pasos()
                ->where('tipo', $tipo->value)
                ->get()
                ->keyBy('id')
                ->all();

            $conservados = [];
            $orden = 0;

            foreach (array_slice($pasos, 0, self::TOPE) as $entrante) {
                $titulo = trim((string) ($entrante['titulo'] ?? ''));

                // Un paso en blanco no se guarda: es una línea que alguien abrió y
                // no llegó a escribir.
                if ($titulo === '') {
                    continue;
                }

                $hecho = (bool) ($entrante['hecho'] ?? false);
                $id = isset($entrante['id']) ? (int) $entrante['id'] : 0;
                $paso = $existentes[$id] ?? null;

                if ($paso instanceof PasoPersona) {
                    $paso->update([
                        'titulo' => $titulo,
                        'orden' => $orden,
                        'hecho_en' => $this->sello($paso, $hecho),
                    ]);

                    $conservados[] = $paso->id;
                } else {
                    $creado = PasoPersona::query()->create([
                        'organizacion_id' => $persona->organizacion_id,
                        'persona_id' => $persona->id,
                        'tipo' => $tipo->value,
                        'titulo' => $titulo,
                        'orden' => $orden,
                        'hecho_en' => $hecho ? Carbon::now() : null,
                    ]);

                    $conservados[] = $creado->id;
                }

                $orden++;
            }

            $persona->pasos()
                ->where('tipo', $tipo->value)
                ->whereNotIn('id', $conservados === [] ? [0] : $conservados)
                ->delete();
        });
    }

    /**
     * La fecha de un paso ya existente: se sella al marcar, se limpia al
     * desmarcar, y **no se vuelve a sellar** si ya estaba puesta.
     */
    private function sello(PasoPersona $paso, bool $hecho): ?Carbon
    {
        if (! $hecho) {
            return null;
        }

        return $paso->hecho_en ?? Carbon::now();
    }
}
