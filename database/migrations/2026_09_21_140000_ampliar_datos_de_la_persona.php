<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos de la persona: identificación, apellidos y contacto.
 *
 * El § 4.8 entró con `nombre` y `email` y poco más, que basta para designar un
 * rol y para pasar lista en una formación y no basta para una ficha de personal.
 *
 * ### `nombre` no desaparece ni cambia de significado: pasa a derivarse
 *
 * Es la decisión que hace barata esta migración. `nombre` lo leen dieciséis
 * sitios, y en `PersonaRecurso` es **columna ordenable, campo de búsqueda y
 * `ordenPorDefecto()`**, así que tiene que seguir siendo una columna real de
 * SQL y no un accesor de PHP: un atributo calculado no se puede ordenar ni meter
 * en un `LIKE` con índice detrás.
 *
 * Y tampoco puede ser una columna que se escriba a mano al lado de las partes:
 * eso es el mismo dato en dos sitios que pueden discrepar, que es justo lo que
 * este repositorio evita con `activa` —que es `fecha_baja IS NULL`—, con
 * `vigente` en el análisis del contexto y con el ámbito de una cuestión del
 * DAFO.
 *
 * Así que lo calcula PostgreSQL: **columna generada `STORED`**. Una definición,
 * en un sitio, imposible de desincronizar.
 *
 * ### Tres cosas que costaron
 *
 * 1. **`concat_ws` NO sirve.** PostgreSQL rechaza la columna con «generation
 *    expression is not immutable»: `concat_ws` acepta `VARIADIC "any"` y su
 *    salida depende de la función de salida de cada tipo —algunas, como
 *    `timestamptz`, dependen de `TimeZone`—, así que está marcada `STABLE`
 *    aunque sólo se le pasen `text`. Una columna generada exige `IMMUTABLE`.
 *    El `coalesce` + `||` + `regexp_replace` de abajo sí lo es, y el
 *    `regexp_replace` **no es adorno**: sin él, un apellido nulo deja el hueco
 *    doble en medio —«Ana  Prat»— que era justo lo que `concat_ws` evitaba
 *    saltándose los nulos.
 * 2. **No hay `ALTER COLUMN … SET GENERATED` para una expresión.** Lo que existe
 *    desde PG 17 reescribe la expresión de una columna que YA es generada, y el
 *    camino inverso —generada a normal— es `DROP EXPRESSION`. De plana a
 *    generada no hay: hay que renombrar y crear al lado.
 * 3. **No hace falta migración de datos, y es deliberado.** El `RENAME` deja el
 *    nombre completo de siempre en `nombre_pila`, los apellidos nacen nulos y la
 *    columna generada reproduce **el mismo texto, byte a byte**. Partir «María
 *    del Carmen de la Fuente Gómez» en tres es una heurística que se equivoca, y
 *    equivocarse aquí cambiaría el nombre que ya está impreso en una designación
 *    firmada. Quien quiera los apellidos separados edita esa ficha.
 *
 *    De paso evita la otra trampa: un `UPDATE` de backfill en una migración
 *    **no ve nada** —no hay petición, no hay contexto, RLS deniega por defecto y
 *    afecta a cero filas sin fallar—, que es lo que ya está escrito en
 *    `2026_09_20_120200_ampliar_calculos_con_personal_formado.php`.
 *
 * ### Protección de datos
 *
 * NIF, fecha de nacimiento, teléfonos y domicilio son datos personales, y la
 * herramienta está en el alcance de su propio SGSI (invariante 8). Ninguno entra
 * en la búsqueda libre de `PersonaRecurso` ni en la exportación a CSV, y sólo el
 * NIF llega a la tabla, como columna **oculta por defecto**. `RegistraTraza`
 * guardará sus valores anteriores en `eventos_auditoria`: es lo correcto para la
 * trazabilidad e implica que el log pasa a contener datos personales, y eso hay
 * que saberlo antes y no descubrirlo en una revisión.
 *
 * **Nota de coste**: `ADD COLUMN … GENERATED … STORED` reescribe la tabla entera
 * con `ACCESS EXCLUSIVE`. En `personas` es irrelevante; a doscientas mil filas
 * no lo sería.
 */
return new class extends Migration
{
    /**
     * El nombre completo, a partir de sus partes.
     *
     * Toda la expresión es `IMMUTABLE`: `coalesce` hereda la volatilidad de sus
     * argumentos, `||` es `textcat`, y `regexp_replace/4` y `btrim/1` lo son.
     */
    private const NOMBRE_COMPLETO = <<<'SQL'
        btrim(regexp_replace(
            coalesce(nombre_pila, '') || ' ' || coalesce(apellido1, '') || ' ' || coalesce(apellido2, ''),
            '\s+', ' ', 'g'))
        SQL;

    public function up(): void
    {
        // El nombre completo de hoy pasa a ser el de pila. El `RENAME` conserva
        // el dato, el tipo y el `NOT NULL`, y no reescribe la tabla.
        DB::statement('ALTER TABLE personas RENAME COLUMN nombre TO nombre_pila');

        // El `CHECK` sigue al renombrado solo, pero su NOMBRE ocupa el que quiere
        // la columna nueva. Se renombra en vez de recrearlo: recrearlo revalida
        // la tabla entera para nada.
        DB::statement('ALTER TABLE personas RENAME CONSTRAINT personas_nombre_check TO personas_nombre_pila_check');

        Schema::table('personas', function (Blueprint $table): void {
            $table->string('apellido1')->nullable();
            $table->string('apellido2')->nullable();

            /*
             * Sin validar la letra: un NIE, un pasaporte y un documento
             * extranjero no la tienen, y rechazarlos sería impedir dar de alta a
             * alguien que trabaja aquí.
             */
            $table->string('nif', 32)->nullable();

            $table->string('telefono', 32)->nullable();
            $table->string('telefono_fijo', 32)->nullable();

            /*
             * Una columna y no cinco. Statera no ordena ni filtra por domicilio
             * ni genera cartas: partirlo en vía, número, código postal, población
             * y provincia son cinco campos que rellenar y cinco formas de dejarlo
             * a medias.
             */
            $table->text('direccion')->nullable();

            $table->date('fecha_nacimiento')->nullable();
        });

        DB::statement(sprintf(
            'ALTER TABLE personas ADD COLUMN nombre varchar(255) GENERATED ALWAYS AS (%s) STORED',
            self::NOMBRE_COMPLETO,
        ));

        // Recupera su nombre sobre la columna nueva. Es redundante mientras
        // `nombre_pila` sea `NOT NULL` y no vacía, pero el nombre de la
        // restricción es lo que se lee en un error de producción.
        DB::statement('ALTER TABLE personas ADD CONSTRAINT personas_nombre_check CHECK (length(btrim(nombre)) > 0)');

        /*
         * Único por organización y **parcial**: en PostgreSQL los nulos son
         * distintos entre sí, así que un índice único a secas ya dejaría pasar a
         * todas las personas sin NIF; el `WHERE` lo deja explícito y de paso no
         * indexa lo que no se consulta. Mismo patrón que
         * `designaciones_rol_titular_unico`.
         */
        DB::statement('CREATE UNIQUE INDEX personas_organizacion_id_nif_unique ON personas (organizacion_id, nif) WHERE nif IS NOT NULL');

        // `nombre` es el `ordenPorDefecto()` del recurso y nunca tuvo índice.
        // Ahora que es estable y generada, merece uno.
        DB::statement('CREATE INDEX personas_organizacion_id_nombre_index ON personas (organizacion_id, nombre)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS personas_organizacion_id_nombre_index');
        DB::statement('DROP INDEX IF EXISTS personas_organizacion_id_nif_unique');
        DB::statement('ALTER TABLE personas DROP CONSTRAINT personas_nombre_check');

        // La generada se va antes que las columnas de las que depende.
        DB::statement('ALTER TABLE personas DROP COLUMN nombre');

        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn([
                'apellido1',
                'apellido2',
                'nif',
                'telefono',
                'telefono_fijo',
                'direccion',
                'fecha_nacimiento',
            ]);
        });

        DB::statement('ALTER TABLE personas RENAME COLUMN nombre_pila TO nombre');
        DB::statement('ALTER TABLE personas RENAME CONSTRAINT personas_nombre_pila_check TO personas_nombre_check');
    }
};
