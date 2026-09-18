<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre el contexto, con el mismo patrón de
 * `habilitar_rls_en_no_conformidades`.
 *
 * Las siete la llevan, **las tres pivotes incluidas**. Es la que se olvida:
 * `RlsDeclaradaTest` enumera las tablas que tienen `organizacion_id` y exige RLS a
 * todas, así que una pivote sin la columna no aparecería en la lista y quedaría
 * abierta sin que nadie lo notara.
 *
 * Y aquí el motivo de fondo pesa más que en ningún otro módulo: el DAFO de una
 * organización es lo más confidencial que va a guardar en esta herramienta. Sus
 * debilidades escritas por ella misma, quién la audita, qué le exige cada
 * regulador y con qué clientes tiene compromisos contractuales. Un análisis de
 * contexto filtrado es material de competencia, no un descuido de privacidad.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'analisis_contexto',
        'cuestiones_contexto',
        'partes_interesadas',
        'requisitos_interesados',
        'cuestion_riesgo',
        'cuestion_tarea',
        'implantacion_requisito_interesado',
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
