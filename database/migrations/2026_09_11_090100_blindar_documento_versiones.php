<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una versión emitida no se modifica, y lo garantiza la base.
 *
 * La regla —«el PDF generado se almacena, no se regenera»— no puede depender de
 * que nadie escriba un `update()` distraído dentro de seis meses. Si la SoA que
 * se entregó al auditor se puede cambiar desde PHP, entonces no se puede
 * demostrar qué se firmó, que es justo lo que esta tabla existe para poder
 * demostrar.
 *
 * **Sólo `UPDATE`, nunca `DELETE`.** El borrado en cascada desde `organizaciones`
 * tiene que seguir funcionando. Es el mismo criterio que con las evidencias: la
 * fila se puede dar de baja; el objeto almacenado es el que no se toca, y de eso
 * responde el Object Lock del bucket.
 *
 * El borrador (`numero IS NULL`) queda fuera: regenerarlo es precisamente lo que
 * se espera de él.
 *
 * `CREATE OR REPLACE` y no `CREATE` a secas: `migrate:fresh` tira las TABLAS,
 * no las funciones, así que la función sobrevive a un refresco de la base y la
 * segunda pasada de las migraciones chocaría con ella. Lo descubrió la suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION documento_version_inmutable() RETURNS trigger AS $$
            BEGIN
                IF OLD.numero IS NOT NULL THEN
                    RAISE EXCEPTION
                        'Una version emitida no se modifica (documento_version %, v%)',
                        OLD.id, OLD.numero;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER documento_versiones_inmutables
                BEFORE UPDATE ON documento_versiones
                FOR EACH ROW EXECUTE FUNCTION documento_version_inmutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS documento_versiones_inmutables ON documento_versiones');
        DB::statement('DROP FUNCTION IF EXISTS documento_version_inmutable()');
    }
};
