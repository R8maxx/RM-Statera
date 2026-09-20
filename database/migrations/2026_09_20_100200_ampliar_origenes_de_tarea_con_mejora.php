<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `mejora` como origen de una tarea. Noveno origen, y el cuarto que no
 * estaba en § 4.7.
 *
 * **Y no se apunta a `no_conformidad`**, que es el que más se le parece: una
 * acción correctiva ataca la causa de algo que incumple, y esto materializa algo
 * que se puede hacer mejor sin que nada incumpla. La cláusula 10.1 y la 10.2 son
 * dos cláusulas distintas precisamente por eso, y colapsar los dos orígenes haría
 * que el reparto del plan de acción contara como reactivo un trabajo que es
 * voluntario — que es justo lo que ese reparto existe para distinguir.
 *
 * Mismo patrón que las tres ampliaciones anteriores: literales en las dos
 * direcciones —enumerar desde el enum haría que `migrate:fresh` incluyera el valor
 * nuevo aunque faltara esta migración, y ningún test se pondría rojo— y el
 * `down()` reasignando las filas antes de reponer el `CHECK` viejo.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        // A `propia` y no borradas, por lo mismo que las anteriores: es trabajo
        // real con su histórico. De dónde venía lo sigue diciendo `mejora_tarea`.
        DB::table('tareas')->where('origen', 'mejora')->update(['origen' => 'propia']);

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
