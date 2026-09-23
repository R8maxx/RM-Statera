<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre `prueba_continuidad_tarea`.
 *
 * `pruebas_continuidad` ya la lleva desde `2026_09_25_090410_…`; esta pivote es
 * nueva y necesita la suya propia, con el mismo patrón que
 * `habilitar_rls_en_mejoras` aplica también a `mejora_tarea`.
 */
return new class extends Migration
{
    private const TABLA = 'prueba_continuidad_tarea';

    public function up(): void
    {
        $tabla = self::TABLA;

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

    public function down(): void
    {
        $tabla = self::TABLA;

        DB::statement("DROP POLICY IF EXISTS {$tabla}_aislamiento ON {$tabla}");
        DB::statement("ALTER TABLE {$tabla} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabla} DISABLE ROW LEVEL SECURITY");
    }
};
