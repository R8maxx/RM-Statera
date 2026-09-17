<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El blindaje de la checklist no funcionaba en `DELETE`.
 *
 * `detalle_de_auditoria_inmutable()` resolvía la auditoría con
 * `COALESCE(NEW.auditoria_id, OLD.auditoria_id)` y cerraba con `RETURN NEW`. Las
 * dos cosas están mal en un trigger de borrado:
 *
 * 1. **En un `DELETE`, PL/pgSQL no asigna `NEW`.** Referenciar un campo suyo
 *    aborta con «record "new" is not assigned yet» **antes** de llegar a mirar si
 *    la auditoría está cerrada. En `INSERT` no se notaba porque `COALESCE`
 *    cortocircuita: evalúa `NEW.auditoria_id`, es no nulo y nunca toca `OLD`.
 * 2. **`RETURN NEW` en un `BEFORE DELETE` devuelve `NULL`**, y un `NULL` ahí
 *    cancela el borrado en silencio. O sea que el camino «correcto» tampoco lo
 *    era: o reventaba con un error que no viene a cuento, o se comía el borrado
 *    sin decir nada.
 *
 * Y el comentario que acompañaba al trigger era falso: decía que el borrado en
 * cascada desde `auditorias` no pasa por aquí «porque la fila padre desaparece».
 * PostgreSQL ejecuta el `DELETE` sobre las filas hijas y **sus triggers de fila
 * se disparan**. Con lo cual, hasta esta migración, **borrar un sistema que
 * tuviera una auditoría registrada reventaba**. No lo cazaba nada porque ningún
 * test de sistemas crea auditorías.
 *
 * Lo que sí protegía bien: `INSERT` y `UPDATE`, que son los dos caminos por los
 * que se reescribe una auditoría entregada. El agujero estaba en el tercero.
 *
 * `CREATE OR REPLACE` y `DROP TRIGGER IF EXISTS` porque la función anterior
 * sobrevive a `migrate:fresh` —que tira las tablas y no las funciones— y porque
 * esta migración corre también sobre bases donde la anterior ya pasó.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION detalle_de_auditoria_inmutable() RETURNS trigger AS $$
            DECLARE
                cerrada boolean;
                fila_id bigint;
            BEGIN
                -- `TG_OP` y no `COALESCE`: en un DELETE, `NEW` no existe.
                IF TG_OP = 'DELETE' THEN
                    fila_id := OLD.auditoria_id;
                ELSE
                    fila_id := NEW.auditoria_id;
                END IF;

                SELECT a.estado = 'cerrada' INTO cerrada FROM auditorias a WHERE a.id = fila_id;

                /*
                 * `cerrada` puede venir a NULL cuando la auditoría ya no está:
                 * es el borrado en cascada, que tiene que seguir funcionando.
                 * `IS TRUE` y no `IF cerrada` para que el nulo no entre.
                 */
                IF cerrada IS TRUE THEN
                    RAISE EXCEPTION
                        'La auditoria % esta cerrada: su checklist y sus hallazgos no se modifican.',
                        fila_id;
                END IF;

                -- En un BEFORE DELETE hay que devolver OLD: un NULL cancelaría el
                -- borrado sin decir nada.
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        // Se vuelve a la versión rota a propósito: revertir una migración tiene
        // que dejar el esquema como estaba, no mejor.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION detalle_de_auditoria_inmutable() RETURNS trigger AS $$
            DECLARE
                cerrada boolean;
                fila_id bigint;
            BEGIN
                fila_id := COALESCE(NEW.auditoria_id, OLD.auditoria_id);

                SELECT a.estado = 'cerrada' INTO cerrada FROM auditorias a WHERE a.id = fila_id;

                IF cerrada THEN
                    RAISE EXCEPTION
                        'La auditoria % esta cerrada: su checklist y sus hallazgos no se modifican.',
                        fila_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }
};
