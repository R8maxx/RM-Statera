<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el registro de personas.
 *
 * **Las seis la llevan.** Es la capa que aquí más pesa de todo el producto: lo que
 * hay en estas tablas son **datos personales** —nombre, puesto, formación,
 * acuerdos firmados, altas y bajas— y filtrarlos no es sólo un problema de
 * confidencialidad de negocio, es una brecha del RGPD con su notificación de 72 h.
 *
 * `users` se queda fuera de las tres capas a propósito —la autenticación tiene que
 * poder encontrar a alguien antes de saber de qué organización es—, y **`personas`
 * no**: aquí no hay ninguna consulta que necesite cruzar la frontera.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'personas',
        'designaciones_rol',
        'acciones_formativas',
        'asistencias',
        'acuerdos_confidencialidad',
        'pasos_persona',
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
