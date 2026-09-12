<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre las listas de comprobación, con el mismo
 * patrón de `2026_09_09_090300_habilitar_row_level_security.php`.
 *
 * Los pasos de una tarea cuentan lo que la organización está haciendo con el
 * detalle que no cabe en el título: leerlos entre tenants filtra tanto como leer
 * la tarea entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subtareas ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE subtareas FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY subtareas_aislamiento ON subtareas
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
        DB::statement('DROP POLICY IF EXISTS subtareas_aislamiento ON subtareas');
        DB::statement('ALTER TABLE subtareas NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE subtareas DISABLE ROW LEVEL SECURITY');
    }
};
