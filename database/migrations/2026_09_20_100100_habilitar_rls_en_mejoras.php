<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre las oportunidades de mejora, con el mismo
 * patrón de `habilitar_rls_en_objetivos`.
 *
 * Las tres la llevan, **la pivote incluida**. Es la que se olvida:
 * `RlsDeclaradaTest` enumera las tablas que tienen `organizacion_id` y exige RLS a
 * todas, así que una pivote sin la columna no aparecería en la lista y quedaría
 * abierta sin que nadie lo notara.
 *
 * Y el motivo de fondo: lo que una organización ha apuntado que puede hacer mejor
 * es un mapa de sus propias carencias escrito por ella misma. Filtrarlo es peor
 * que filtrar su registro de no conformidades, porque aquél lo encontró un auditor
 * y esto lo escribieron ellos.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['mejoras', 'mejora_tarea', 'mejora_transiciones'];

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
