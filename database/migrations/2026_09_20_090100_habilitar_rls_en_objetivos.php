<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre los objetivos de seguridad, con el mismo
 * patrón de `habilitar_rls_en_no_conformidades`.
 *
 * Las cuatro la llevan, **las dos pivotes incluidas**. Son las que se olvidan:
 * `RlsDeclaradaTest` enumera las tablas que tienen `organizacion_id` y exige RLS a
 * todas, así que una pivote sin la columna no aparecería en la lista y quedaría
 * abierta sin que nadie lo notara.
 *
 * Y el motivo de fondo es el de siempre: los objetivos de seguridad de una
 * organización son lo que su dirección se ha comprometido a conseguir este año.
 * Saber a qué se ha comprometido el cliente de al lado —y cuáles no ha
 * alcanzado— filtra tanto como leer su acta de revisión entera.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['objetivos_seguridad', 'indicador_objetivo', 'objetivo_tarea', 'objetivo_transiciones'];

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
