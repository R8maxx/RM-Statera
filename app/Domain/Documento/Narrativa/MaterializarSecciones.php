<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use Illuminate\Support\Facades\DB;

/**
 * Copia la plantilla de la organización a un documento concreto.
 *
 * A partir de aquí los textos son **un hecho del documento**: si la plantilla
 * cambia después, este documento no se entera. Es deliberado, y es el mismo
 * razonamiento que hay detrás de `documento_versiones.instantanea` — lo que dice
 * un documento no puede ser el resultado de un join que cambie bajo los pies.
 *
 * **Idempotente**: sólo inserta lo que falta y no sobrescribe nunca. Se llama al
 * crear el documento, al abrir su editor por primera vez y desde el comando de
 * mantenimiento, y las tres veces tiene que poder ejecutarse sin miedo.
 */
final readonly class MaterializarSecciones
{
    public function __construct(private ResolverNarrativa $resolver) {}

    /**
     * @return int Cuántos huecos se han materializado.
     */
    public function __invoke(Documento $documento): int
    {
        // `seccion` va casteada a enum: `pluck` devuelve objetos, no cadenas, y
        // el `in_array` de abajo no acertaría nunca. Se materializaría otra vez
        // en cada pasada hasta chocar con el índice único.
        $existentes = $documento->secciones()->get()
            ->map(static fn (DocumentoSeccion $fila): string => $fila->seccion->value)
            ->all();
        $plantilla = $this->resolver->paraPlantilla($documento->tipo);

        $faltan = array_filter(
            SeccionNarrativa::paraTipo($documento->tipo),
            static fn (SeccionNarrativa $seccion): bool => ! in_array($seccion->value, $existentes, true),
        );

        if ($faltan === []) {
            return 0;
        }

        return DB::transaction(function () use ($documento, $faltan, $plantilla): int {
            foreach ($faltan as $seccion) {
                /*
                 * `create()` fila a fila, y no un `insert()` en bloque: el trait
                 * `PerteneceAOrganizacion` rellena `organizacion_id` en el evento
                 * `creating`, y tanto `DB::table()->insert()` como
                 * `Model::insert()` SE SALTAN LOS EVENTOS. La columna se quedaría
                 * a nulo y RLS rechazaría la fila con un error de privilegios que
                 * no menciona la palabra «organización» por ninguna parte. Es el
                 * mismo fallo que ya obligó a quitar `WithoutModelEvents` del
                 * `DatabaseSeeder`.
                 *
                 * Son diez u once filas: el bloque no compra nada.
                 */
                DocumentoSeccion::query()->create([
                    'documento_id' => $documento->id,
                    'seccion' => $seccion->value,
                    'contenido_md' => $plantilla[$seccion->value] ?? '',
                    'origen' => OrigenTexto::Plantilla->value,
                ]);
            }

            return count($faltan);
        });
    }

    /**
     * Tira las secciones que han dejado de aplicar y materializa las que ahora
     * apliquen.
     *
     * Hace falta porque el tipo de un documento se puede cambiar: una SoA que
     * pasa a DdA arrastraría su `nota_exclusiones` invisible y la recuperaría —
     * con un texto de hace meses— si alguien la devolviera a SoA.
     */
    public function sincronizarConElTipo(Documento $documento): void
    {
        $validas = array_map(
            static fn (SeccionNarrativa $seccion): string => $seccion->value,
            SeccionNarrativa::paraTipo($documento->tipo),
        );

        $documento->secciones()->whereNotIn('seccion', $validas)->delete();

        $this($documento);
    }
}
