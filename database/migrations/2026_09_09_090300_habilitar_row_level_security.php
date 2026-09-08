<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento multi-tenant (§5 del stack).
 *
 * Las otras dos —`organizacion_id` y el global scope de Eloquent— viven en la
 * aplicación y se pueden esquivar: basta un `withoutGlobalScopes()` de más o un
 * `DB::table()` en crudo. Ésta vive en PostgreSQL y no.
 *
 * Tres detalles que no son accidentales:
 *
 * 1. `FORCE` es obligatorio. Sin él PostgreSQL exime al propietario de la tabla,
 *    y el rol `statera` de docker-compose.yml es precisamente el propietario: la
 *    política existiría y no haría nada, que es peor que no tenerla.
 *
 * 2. Sin contexto no se ve nada. `current_setting(..., true)` devuelve NULL
 *    cuando la variable no está puesta y la comparación con NULL no casa
 *    ninguna fila. Denegar por defecto obliga a que cada consulta declare su
 *    organización.
 *
 * 3. `app.mantenimiento` es la única puerta, y existe porque ya hace falta: el
 *    importador del catálogo tiene que contar implantaciones de TODAS las
 *    organizaciones para informar de a qué afecta una revisión del marco. La
 *    abre ContextoOrganizacion::comoMantenimiento() y nada más.
 */
return new class extends Migration
{
    /** @var list<string> Tablas de datos propios. `organizaciones` no entra: es la raíz del tenant. */
    private const TABLAS = [
        'sistemas',
        'valoracion_dimensiones',
        'implantaciones',
        'implantacion_transiciones',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabla} FORCE ROW LEVEL SECURITY");

            DB::statement(<<<SQL
                CREATE POLICY {$tabla}_aislamiento ON {$tabla}
                USING (
                    organizacion_id = NULLIF(current_setting('app.organizacion_actual', true), '')::bigint
                    OR current_setting('app.mantenimiento', true) = 'on'
                )
                WITH CHECK (
                    organizacion_id = NULLIF(current_setting('app.organizacion_actual', true), '')::bigint
                    OR current_setting('app.mantenimiento', true) = 'on'
                )
            SQL);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::statement("DROP POLICY IF EXISTS {$tabla}_aislamiento ON {$tabla}");
            DB::statement("ALTER TABLE {$tabla} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabla} DISABLE ROW LEVEL SECURITY");
        }
    }
};
