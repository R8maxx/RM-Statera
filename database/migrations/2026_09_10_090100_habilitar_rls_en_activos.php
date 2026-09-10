<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el inventario, con el mismo patrón de
 * `2026_09_09_110100_habilitar_rls_en_evidencias.php`.
 *
 * Las dos pivotes la llevan igual que la tabla principal. En el grafo de
 * dependencias importa especialmente: un vínculo dice qué sostiene a qué en una
 * organización concreta, y ese mapa es exactamente lo que buscaría quien
 * quisiera saber por dónde atacar.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['activos', 'activo_dependencias', 'activo_sistema'];

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
