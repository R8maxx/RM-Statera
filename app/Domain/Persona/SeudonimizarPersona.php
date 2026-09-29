<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Copia\EspejoDeObjetos;
use App\Domain\Persona\Excepciones\SeudonimizacionNoPermitida;
use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Suprime los datos personales de alguien que ya no trabaja aquí (§ 6, punto 36).
 *
 * **Seudonimiza, no borra.** La fila se queda como «Persona PER-042» porque de
 * ella cuelgan nombramientos, formación, acuses de lectura y checklists de
 * salida, y todo eso es histórico del SGSI: el auditor sigue pudiendo ver que
 * `mp.per.4` se cumplía en marzo, aunque ya no sepa con quién. Lo que se va es lo
 * que identifica a la persona: nombre, documento, teléfonos, domicilio, fecha de
 * nacimiento, correo, notas, el vínculo con su cuenta y sus adjuntos.
 *
 * **Y se va de la traza**, que es lo que hace que esto sea una supresión y no un
 * cambio de nombre. `depurar_traza_de_persona()` quita esas claves de los eventos
 * de la persona y de sus adjuntos, incluido el que deja esta misma operación. Es
 * la única puerta a `eventos_auditoria` que tiene la aplicación, y lo que puede
 * hacer está escrito en su migración.
 *
 * Dos condiciones, y las dos impiden en vez de avisar: que tenga fecha de baja y
 * que no le quede ningún nombramiento vigente. Un rol del ENS asignado a
 * «Persona PER-042» sería un hallazgo que la herramienta habría fabricado.
 *
 * Lo que **no** alcanza está en `personas.md`: la cuenta de `users`, las
 * evidencias que la nombren y los volcados de copia de los últimos días.
 */
final readonly class SeudonimizarPersona
{
    public function __invoke(Persona $persona): Persona
    {
        $this->comprobar($persona);

        $adjuntos = $persona->adjuntos()->get();
        /** @var list<array{disco: string, ruta: string}> $ficheros */
        $ficheros = $adjuntos->map(static fn (Adjunto $adjunto): array => ['disco' => $adjunto->disco, 'ruta' => $adjunto->ruta])->all();

        DB::transaction(function () use ($persona, $adjuntos): void {
            // Las pivotes se van en cascada, como en `BorrarAdjunto`.
            $adjuntos->each->delete();

            $persona->forceFill([
                'nombre_pila' => "Persona {$persona->codigo}",
                'apellido1' => null,
                'apellido2' => null,
                'nif' => null,
                'nif_huella' => null,
                'telefono' => null,
                'telefono_fijo' => null,
                'direccion' => null,
                'fecha_nacimiento' => null,
                'email' => null,
                'notas' => null,
                'user_id' => null,
                'seudonimizada_en' => Carbon::now(),
            ])->save();

            // Después de guardar: así se depura también el evento que acaba de
            // dejar el propio `save()`, que lleva en `valor_anterior` todo lo que
            // se estaba quitando.
            DB::select('select depurar_traza_de_persona(?, ?::bigint[])', [
                $persona->id,
                '{'.implode(',', $adjuntos->modelKeys()).'}',
            ]);
        });

        /*
         * Los ficheros, después de confirmar y no antes, por lo mismo que en
         * `BorrarAdjunto`: si la transacción fallara, las filas volverían y
         * apuntarían a objetos que ya no existen. Y también del espejo de
         * copias, que nunca borra por su cuenta: aquí hay que hacerlo, o el
         * adjunto se quedaría para siempre.
         */
        foreach ($ficheros as ['disco' => $disco, 'ruta' => $ruta]) {
            Storage::disk($disco)->delete($ruta);
            Storage::disk(config('copias.disco'))->delete(EspejoDeObjetos::rutaEnCopia($disco, $ruta));
        }

        return $persona->refresh();
    }

    private function comprobar(Persona $persona): void
    {
        if ($persona->seudonimizada_en !== null) {
            throw SeudonimizacionNoPermitida::yaSeudonimizada($persona->codigo);
        }

        if ($persona->fecha_baja === null || $persona->fecha_baja->isFuture()) {
            throw SeudonimizacionNoPermitida::sigueEnPlantilla($persona->nombre);
        }

        // `reorder()`: la relación viene ordenada, y PostgreSQL no admite
        // `ORDER BY` en un recuento.
        $vigentes = $persona->designaciones()->vigentes()->reorder()->count();

        if ($vigentes > 0) {
            throw SeudonimizacionNoPermitida::conNombramientos($persona->nombre, $vigentes);
        }
    }
}
