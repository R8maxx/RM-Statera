<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el registro de revisiones, con el mismo
 * patrón de `2026_09_10_090100_habilitar_rls_en_activos.php`.
 *
 * Lo que hay aquí son las desviaciones detectadas en el inventario de un
 * cliente: dónde está lo que falla y qué se ha acordado hacer al respecto. Es
 * de lo más sensible que guarda la herramienta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE revisiones_inventario ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE revisiones_inventario FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY revisiones_inventario_aislamiento ON revisiones_inventario
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
        DB::statement('DROP POLICY IF EXISTS revisiones_inventario_aislamiento ON revisiones_inventario');
        DB::statement('ALTER TABLE revisiones_inventario NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE revisiones_inventario DISABLE ROW LEVEL SECURITY');
    }
};
