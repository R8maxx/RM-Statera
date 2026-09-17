<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre las no conformidades, con el mismo patrón de
 * `habilitar_rls_en_auditorias`.
 *
 * Las tres la llevan, **la pivote incluida**. Es la que se olvida: `RlsDeclaradaTest`
 * enumera las tablas que tienen `organizacion_id` y exige RLS a todas, así que una
 * pivote sin la columna no aparecería en la lista y quedaría abierta sin que nadie
 * lo notara. Es el mismo caso que `implantacion_tarea`, y por eso las dos la llevan.
 *
 * Y el motivo de fondo es el de siempre: saber que la organización de al lado tiene
 * cuatro no conformidades mayores abiertas sobre control de acceso filtra tanto como
 * leer su informe de auditoría entero.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['no_conformidades', 'no_conformidad_tarea', 'no_conformidad_transiciones'];

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
