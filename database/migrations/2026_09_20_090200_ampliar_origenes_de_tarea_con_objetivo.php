<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `objetivo` como origen de una tarea. Octavo origen, y el tercero que no
 * estaba en § 4.7.
 *
 * Los dos anteriores fueron `propia` —porque muchas tareas no nacen de ningún
 * registro— y `contexto` —porque nace de un registro que § 4.7 no previó—. Éste
 * es del segundo tipo: «qué se hará» es literalmente lo primero que la cláusula
 * 6.2 pide de la planificación de un objetivo, y sin un valor propio esas
 * actuaciones se apuntarían como `propia`, que es el «elegir el que menos mal
 * suena» que deja el campo sin significar nada.
 *
 * **Y no se apunta a `brecha_implantacion`**, que es el que más se le parece: una
 * brecha es una medida exigible que no está implantada, con su requisito detrás.
 * Un objetivo de seguridad no cuelga de ningún requisito —puede cumplirse sin
 * mover una sola implantación, y de hecho los más interesantes son así—.
 *
 * Mismo patrón que las dos ampliaciones anteriores: literales en las dos
 * direcciones —enumerar desde el enum haría que `migrate:fresh` incluyera el valor
 * nuevo aunque faltara esta migración, y ningún test se pondría rojo— y el
 * `down()` reasignando las filas antes de reponer el `CHECK` viejo, o el
 * `ALTER TABLE` no valida y la migración se queda a medias.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'contexto', 'incidente', 'revision_direccion', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        // A `propia` y no borradas, por lo mismo que las acciones correctivas y
        // las del contexto: es trabajo real con su histórico y puede que con su
        // coste presupuestado. De dónde venía lo sigue diciendo `objetivo_tarea`.
        DB::table('tareas')->where('origen', 'objetivo')->update(['origen' => 'propia']);

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
