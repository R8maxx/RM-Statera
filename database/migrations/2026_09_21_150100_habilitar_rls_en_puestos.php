<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tercera capa del aislamiento sobre puestos y asignaciones.
 *
 * Va en **fichero aparte y antes de mover los datos**, que es la convención del
 * repositorio (`create_X_tables` → `habilitar_rls_en_X`) y aquí además tiene un
 * efecto práctico: con las políticas ya puestas, la migración de datos que viene
 * detrás **demuestra en el propio `migrate` que necesita
 * `comoMantenimiento()`**. Si insertáramos antes de la política, el fallo no
 * aparecería hasta que alguien insertara desde la aplicación, meses después.
 *
 * `FORCE` no es decorativo y es el que se olvida: `statera_app` es el propietario
 * del esquema y sin él queda exento, así que la tabla parecería protegida sin
 * estarlo. `RlsDeclaradaTest` lo comprueba preguntando a `pg_class`, no
 * enumerando modelos, así que estas dos tablas entran en el barrido solas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'puestos',
        'asignaciones_puesto',
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
