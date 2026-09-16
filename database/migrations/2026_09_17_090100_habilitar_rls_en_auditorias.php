<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre las auditorías, con el mismo patrón de
 * `habilitar_rls_en_tareas`.
 *
 * Las tres la llevan, y las dos de detalle por el mismo motivo que la principal:
 * saber que la organización de al lado tiene tres no conformidades mayores
 * abiertas sobre control de acceso filtra tanto como leer la auditoría entera.
 *
 * `RlsDeclaradaTest` ya exige esto por introspección, así que olvidarlo pone la
 * suite en rojo en vez de dejar tres tablas abiertas en silencio.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['auditorias', 'auditoria_puntos', 'hallazgos'];

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
