<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre las revisiones por la dirección.
 *
 * Las dos la llevan, **la pivote incluida**, por lo mismo de siempre:
 * `RlsDeclaradaTest` enumera las tablas que tienen `organizacion_id` y exige RLS a
 * todas, así que una pivote sin la columna no aparecería en la lista y quedaría
 * abierta sin que nadie lo notara.
 *
 * Y el motivo de fondo es el más claro de todo el producto: el acta de una
 * revisión por la dirección contiene, congeladas, **las siete entradas de la 9.3**
 * de esa organización —sus no conformidades, sus riesgos, sus auditorías y sus
 * objetivos—. Filtrar una fila de aquí es filtrar el SGSI entero de un cliente en
 * un solo documento.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = ['revisiones_direccion', 'revision_tarea'];

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
