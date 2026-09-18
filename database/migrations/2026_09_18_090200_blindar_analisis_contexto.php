<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El **cuarto trigger de inmutabilidad** del producto, hermano de los de
 * `documento_versiones`, `riesgo_valoraciones` y `auditorias`.
 *
 * Un análisis del contexto aprobado es lo que la organización declaró que era su
 * situación ese día, y es una entrada obligatoria de la revisión por la dirección.
 * Si se pudiera reescribir desde PHP, bastaría con retocar el análisis del año
 * pasado para que la revisión de la dirección hubiera hablado de otra cosa — que
 * es exactamente el movimiento que estas tablas existen para hacer imposible.
 *
 * **La ventana editable es el borrador**, y nada más. Mientras `numero` es nulo se
 * retoca cuantas veces haga falta: es precisamente lo que se espera de él, y
 * mientras no tenga número no se ha declarado nada.
 *
 * **Una sola puerta, y la pone el sistema**: `aprobado → obsoleto` al aprobarse el
 * siguiente análisis. Es la misma que se les talló a `riesgo_valoraciones` con
 * `vigente` y a `documento_versiones` con `estado`, y hace falta sí o sí: sin ella
 * un contexto aprobado **no podría revisarse nunca**, que es lo contrario de lo
 * que pide la cláusula 9.3.
 *
 * **Se compara el registro entero con `estado` neutralizado**, en vez de enumerar
 * qué se puede cambiar: una columna nueva quedaría fuera de la lista y sería
 * editable sin que nadie lo notara.
 *
 * Y `updated_at` se neutraliza **sólo cuando el estado cambia**. Es la marca de
 * tiempo de esa misma jubilación, así que sin esto la escritura legítima fallaría;
 * neutralizarla siempre dejaría pasar un «toque» suelto sobre una fila firmada, y
 * una fila que se puede tocar es una fila que alguien acabará tocando.
 *
 * **Sólo `UPDATE`, nunca `DELETE`**: el borrado en cascada desde `organizaciones`
 * tiene que seguir funcionando. Que un análisis firmado no se borre a mano lo
 * impide el dominio, y de rebote las dos foráneas `restrictOnDelete` que le
 * apuntan desde las cuestiones y las partes.
 *
 * `CREATE OR REPLACE` y no `CREATE` a secas: `migrate:fresh` tira las TABLAS y no
 * las funciones, así que la función sobrevive a un refresco de la base y la
 * segunda pasada chocaría con ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION analisis_contexto_inmutable() RETURNS trigger AS $$
            DECLARE
                comparable analisis_contexto;
            BEGIN
                IF OLD.numero IS NULL THEN
                    RETURN NEW;
                END IF;

                comparable := NEW;
                comparable.estado := OLD.estado;

                IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                    comparable.updated_at := OLD.updated_at;
                END IF;

                IF comparable IS DISTINCT FROM OLD THEN
                    RAISE EXCEPTION
                        'Un analisis del contexto aprobado no se modifica (analisis_contexto %, numero %)',
                        OLD.id, OLD.numero;
                END IF;

                IF NEW.estado IS DISTINCT FROM OLD.estado
                   AND NOT (OLD.estado = 'aprobado' AND NEW.estado = 'obsoleto') THEN
                    RAISE EXCEPTION
                        'Un analisis aprobado solo puede pasar a obsoleto (analisis_contexto %, numero %: % -> %)',
                        OLD.id, OLD.numero, OLD.estado, NEW.estado;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER analisis_contexto_inmutables
                BEFORE UPDATE ON analisis_contexto
                FOR EACH ROW EXECUTE FUNCTION analisis_contexto_inmutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS analisis_contexto_inmutables ON analisis_contexto');
        DB::statement('DROP FUNCTION IF EXISTS analisis_contexto_inmutable()');
    }
};
