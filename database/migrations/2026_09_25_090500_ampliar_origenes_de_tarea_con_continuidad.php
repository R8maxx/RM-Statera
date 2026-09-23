<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `continuidad` como origen de una tarea. Décimo origen, y el quinto que
 * no estaba en § 4.7.
 *
 * Una prueba de un plan de continuidad que sale parcial o fallida —`op.cont.3`—
 * deja trabajo correctivo directo, igual que un incidente: «revisar el guion de
 * failover» no espera a ninguna no conformidad, porque puede que no llegue a
 * haberla. A diferencia de `incidente`, aquí sí hay pivote —`prueba_continuidad_
 * tarea`, en la migración siguiente— porque este módulo necesita poder contar
 * cuánto trabajo dejó cada prueba.
 *
 * Mismo patrón que las ampliaciones anteriores de este `CHECK`: literales en las
 * dos direcciones —enumerar desde el enum haría que `migrate:fresh` incluyera el
 * valor nuevo aunque faltara esta migración, y ningún test se pondría rojo— y el
 * `down()` reasignando las filas antes de reponer el `CHECK` viejo.
 *
 * **Y por `ContextoOrganizacion::comoMantenimiento()`**, a diferencia de la
 * migración de `mejora` que ésta calca: una migración no tiene petición ni
 * usuario, así que sin mantenimiento RLS deniega por defecto, el `update` afecta
 * a cero filas **sin fallar** y el `ALTER TABLE` siguiente muere con «is violated
 * by some row» sobre las filas de organizaciones que el `update` nunca tocó. Es
 * el mismo fallo que ya corrigió `2026_09_20_130300_…con_incidente.php`.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'continuidad', 'propia'";

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
        // real con su histórico. De dónde venía lo sigue diciendo
        // `prueba_continuidad_tarea`.
        app(ContextoOrganizacion::class)->comoMantenimiento(
            static fn () => DB::table('tareas')->where('origen', 'continuidad')->update(['origen' => 'propia']),
        );

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
