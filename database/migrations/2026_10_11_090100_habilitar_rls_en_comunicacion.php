<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre la comunicación del SGSI (7.4), con el
 * mismo patrón de `habilitar_rls_en_mejoras`. Lo recibido —quejas, encuestas—
 * es de lo más sensible que una organización guarda sobre sí misma.
 *
 * Las tres la llevan, **la pivote incluida**: es la que se olvida, y
 * `RlsDeclaradaTest` no la vería si le faltara también `organizacion_id`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['comunicaciones_previstas', 'comunicacion_prevista_parte_interesada', 'comunicaciones'];

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
