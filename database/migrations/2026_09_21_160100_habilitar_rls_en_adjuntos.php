<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre los adjuntos.
 *
 * Pesa aquí tanto como en personas y por lo mismo: lo que hay dentro de estos
 * ficheros son **datos personales** —un DNI escaneado, un contrato firmado, un
 * título—, y filtrarlos no es un problema de confidencialidad de negocio, es una
 * brecha del RGPD con su notificación de 72 h.
 *
 * `FORCE` no es decorativo y es el que se olvida: `statera_app` es el propietario
 * del esquema y sin él queda exento, así que la tabla parecería protegida sin
 * estarlo. Lo comprueba `RlsDeclaradaTest` preguntando a `pg_class`, de modo que
 * estas tres tablas entran en el barrido solas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'adjuntos',
        'persona_adjunto',
        'accion_formativa_adjunto',
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
