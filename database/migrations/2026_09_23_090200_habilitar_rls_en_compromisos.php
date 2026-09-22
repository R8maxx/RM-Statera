<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre los compromisos, con el mismo patrón de
 * `habilitar_rls_en_mejoras`.
 *
 * **`obligaciones` no entra, y es correcto**: es catálogo global, no tiene
 * `organizacion_id` y `RlsDeclaradaTest` no la reclamará porque interroga a
 * `information_schema` por esa columna. Es lo mismo que pasa con `marcos`,
 * `requisitos` y `amenazas`.
 *
 * Lo que sí entra es lo que una organización ha asumido y cuándo lo cumplió. El
 * calendario de otro cliente dice qué se le ha pasado de fecha, que es la lista de
 * sus incumplimientos escrita por él mismo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['compromisos', 'compromiso_cumplimientos'];

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
