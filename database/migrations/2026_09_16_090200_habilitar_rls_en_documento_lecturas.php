<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el acuse de lectura, con el mismo patrón de
 * `2026_09_11_090200_habilitar_rls_en_documentos.php`.
 *
 * Va aparte de la migración que crea la tabla por lo de siempre: quien revise una
 * entrega tiene que poder ver de un vistazo qué tablas ganaron política y cuáles
 * no, sin leerse las migraciones de esquema enteras.
 *
 * Y esta tabla lo pide por partida doble, porque no habla de documentos sino de
 * **personas**: quién ha leído qué y cuándo. Es un registro de conducta de los
 * empleados de un cliente, y es de lo último que debería poder verse desde otra
 * organización.
 */
return new class extends Migration
{
    private const TABLA = 'documento_lecturas';

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
