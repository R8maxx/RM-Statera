<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La tercera capa del aislamiento sobre la traza, con el mismo patrón de
 * `2026_09_09_090300_habilitar_row_level_security.php`.
 *
 * Aquí importa más que en ninguna otra tabla: el log de auditoría guarda los
 * valores anteriores y nuevos de los datos de cumplimiento de un cliente. Una
 * traza que se lea entre organizaciones filtra exactamente lo que las tres capas
 * protegen en las tablas de origen.
 */
return new class extends Migration
{
    private const TABLA = 'eventos_auditoria';

    public function up(): void
    {
        DB::statement('ALTER TABLE '.self::TABLA.' ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE '.self::TABLA.' FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY eventos_auditoria_aislamiento ON eventos_auditoria
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
        DB::statement('DROP POLICY IF EXISTS eventos_auditoria_aislamiento ON '.self::TABLA);
        DB::statement('ALTER TABLE '.self::TABLA.' NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE '.self::TABLA.' DISABLE ROW LEVEL SECURITY');
    }
};
