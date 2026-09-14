<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el análisis de riesgos, con el mismo patrón
 * de `2026_09_09_090300_habilitar_row_level_security.php`.
 *
 * Las cinco tablas de organización la llevan, pivotes e histórico incluidos. Y
 * aquí importa más que en ningún otro módulo: el análisis de riesgos de una
 * organización es el documento que dice por dónde se la puede atacar y qué ha
 * decidido no arreglar. Saber que la organización de al lado tiene un riesgo muy
 * alto aceptado sobre su control de acceso no es un dato de menos valor que
 * leerlo entero.
 *
 * **`amenazas` NO entra en esta lista y no es un olvido**: es catálogo global,
 * compartido entre tenants y sin `organizacion_id` (invariante 2). Ponerle una
 * política la dejaría vacía para todo el mundo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'metodologias_riesgo',
        'riesgos',
        'activo_riesgo',
        'riesgo_implantacion',
        'riesgo_valoraciones',
    ];

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
