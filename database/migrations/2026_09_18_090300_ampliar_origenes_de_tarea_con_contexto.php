<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `contexto` como origen de una tarea. Séptimo origen, y el segundo que no
 * estaba en § 4.7.
 *
 * El primero fue `propia`, que se añadió porque muchas tareas no nacen de ningún
 * registro. Éste se añade por lo contrario: nace de un registro que § 4.7 no
 * previó porque su módulo no existía. Una debilidad del DAFO —«no tenemos
 * inventariado el software de los puestos»— es trabajo que hay que hacer, y con
 * los siete orígenes anteriores habría que apuntarla como `propia`, que es
 * exactamente el «elegir el que menos mal suena» que deja el campo sin significar
 * nada.
 *
 * **Y no se apunta a `hallazgo` ni a `riesgo`**, que son los dos que más se
 * parecen: un hallazgo sale de auditar contra un requisito y un riesgo tiene
 * probabilidad, impacto y una decisión de tratamiento detrás. Una cuestión del
 * contexto no tiene ninguna de las dos cosas — puede acabar generando un riesgo,
 * y entonces la tarea de ese riesgo es otra tarea.
 *
 * Mismo patrón que `ampliar_origenes_de_tarea`: literales en las dos direcciones
 * —enumerar desde el enum haría que `migrate:fresh` incluyera el valor nuevo
 * aunque faltara esta migración, y ningún test se pondría rojo— y el `down()`
 * reasignando las filas antes de reponer el `CHECK` viejo, o el `ALTER TABLE` no
 * valida y la migración se queda a medias.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'incidente', 'revision_direccion', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'riesgo', 'brecha_implantacion', 'contexto', 'incidente', 'revision_direccion', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        // A `propia` y no borradas, por lo mismo que las acciones correctivas: es
        // trabajo real con su histórico y puede que con su coste presupuestado.
        // De dónde venía lo sigue diciendo la pivote `cuestion_tarea`.
        DB::table('tareas')->where('origen', 'contexto')->update(['origen' => 'propia']);

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
