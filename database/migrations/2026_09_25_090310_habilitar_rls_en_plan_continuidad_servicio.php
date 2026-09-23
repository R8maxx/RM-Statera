<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre `plan_continuidad_servicio`.
 *
 * Qué servicio cubre el plan de continuidad de un cliente es información de
 * su propio análisis de continuidad, igual que el BIA del que sale; filtrarla
 * a otra organización es de lo peor que este producto puede hacer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_continuidad_servicio ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE plan_continuidad_servicio FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY plan_continuidad_servicio_aislamiento ON plan_continuidad_servicio
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
        DB::statement('DROP POLICY IF EXISTS plan_continuidad_servicio_aislamiento ON plan_continuidad_servicio');
        DB::statement('ALTER TABLE plan_continuidad_servicio NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE plan_continuidad_servicio DISABLE ROW LEVEL SECURITY');
    }
};
