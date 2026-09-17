<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `no_conformidad` como origen de una tarea.
 *
 * **Y es el sexto origen, no el primero cableado de los cinco de § 4.7.** La
 * distinción importa y está en el corazón de este módulo: § 4.7 enumera
 * «hallazgo» como origen, y una tarea **no cuelga nunca de un hallazgo**. Cuelga
 * de la no conformidad que lo trata, que es quien tiene la causa raíz, el
 * responsable y la fecha de verificación. Entre el hallazgo y la tarea hay
 * exactamente un registro, y saltárselo dejaría el trabajo correctivo sin nada
 * que explique por qué se hace.
 *
 * `OrigenTarea::Hallazgo` se queda declarado y **sigue sin ofrecerse**, igual que
 * incidente y revisión por la dirección: el modelo entero desde el principio y
 * los datos que haya. Lo que cambia es el motivo por el que no se ofrece —ya no
 * es «espera a § 4.12», que llegó, sino que hay un eslabón por medio—, y eso está
 * reescrito en el enum.
 *
 * Patrón de `ampliar_tipos_con_plan_de_adecuacion`: literales en las dos
 * direcciones —porque enumerar desde el enum haría que `migrate:fresh` incluyera
 * el valor nuevo aunque faltara esta migración, y ningún test se pondría rojo— y
 * el `down()` borrando las filas del valor nuevo antes de reponer el `CHECK`
 * viejo, o el `ALTER TABLE` no valida y la migración se queda a medias.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'riesgo', 'brecha_implantacion', 'incidente', 'revision_direccion', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'incidente', 'revision_direccion', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        /*
         * Las tareas del origen nuevo pasan a `propia` en vez de borrarse. Una
         * acción correctiva es trabajo real, con su histórico y puede que con su
         * coste presupuestado; tirarla para poder reponer un `CHECK` sería perder
         * el dato por un detalle del esquema. Lo que se pierde al revertir es de
         * dónde venía, y eso lo sigue diciendo la pivote `no_conformidad_tarea`.
         */
        DB::table('tareas')->where('origen', 'no_conformidad')->update(['origen' => 'propia']);

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
