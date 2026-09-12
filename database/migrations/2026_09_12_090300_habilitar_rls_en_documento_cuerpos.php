<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el cuerpo de los documentos, con el mismo
 * patrón de `2026_09_11_090200_habilitar_rls_en_documentos.php`.
 *
 * Va aparte de la migración que crea la tabla por lo de siempre: quien revise
 * una entrega tiene que poder ver de un vistazo qué tablas ganaron política y
 * cuáles no, sin leerse las migraciones de esquema enteras. Y esta tabla es de
 * las que más lo piden: contiene el documento entero —los controles excluidos y
 * sus motivos, las evidencias, las limitaciones que la organización reconoce por
 * escrito—, que es exactamente lo que un cliente no querría ver en manos de otro.
 */
return new class extends Migration
{
    private const TABLA = 'documento_cuerpos';

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
