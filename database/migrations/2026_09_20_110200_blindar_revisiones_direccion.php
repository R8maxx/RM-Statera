<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El **quinto trigger de inmutabilidad** del producto, hermano de los de
 * `documento_versiones`, `riesgo_valoraciones`, `auditorias` y `analisis_contexto`.
 *
 * Un acta de revisión por la dirección aprobada es lo que la dirección declaró que
 * había revisado y qué decidió, con su fecha y su firma. Si se pudiera reescribir
 * desde PHP, bastaría con retocar el acta del año pasado para que la dirección
 * hubiera hablado de otra cosa —o para añadirle una decisión que nunca tomó—, que
 * es exactamente el movimiento que estas tablas existen para hacer imposible.
 *
 * **La ventana editable es todo lo anterior a la aprobación.** Mientras la revisión
 * está `planificada` o `en_curso` se retoca cuantas veces haga falta: es
 * precisamente lo que se espera de ella, y mientras no esté aprobada no se ha
 * declarado nada.
 *
 * **Una sola puerta: `aprobada → en_curso`.** Es la misma que tiene `auditorias`
 * con `cerrada → en_curso`, y hace falta por lo mismo: un acta que se firmó con un
 * error tiene que poder corregirse, y la alternativa —no poder tocarla nunca— lleva
 * a que alguien abra una revisión nueva para arreglar la anterior y el histórico
 * cuente dos reuniones donde hubo una. Lo que **no** se puede es volver a
 * `planificada`: decir que una revisión que se celebró está sin celebrar es
 * reescribir el pasado, igual que devolver una auditoría cerrada a `planificada`.
 *
 * **La firma y la instantánea NO se neutralizan al reabrir**, y por eso el
 * `CHECK` de la firma va en una sola dirección: la fila reabierta conserva quién
 * la aprobó y qué se congeló hasta que la siguiente aprobación lo sobreescribe. Al
 * revés —limpiarlas al reabrir— habría que hacerlo en la misma escritura que este
 * trigger está vigilando, y el trigger la rechazaría.
 *
 * **Se compara el registro entero con `estado` neutralizado**, en vez de enumerar
 * qué se puede cambiar: una columna nueva quedaría fuera de la lista y sería
 * editable sin que nadie lo notara.
 *
 * Y `updated_at` se neutraliza **sólo cuando el estado cambia**, por lo mismo que
 * en `analisis_contexto`: es la marca de tiempo de esa misma reapertura, así que
 * sin esto la escritura legítima fallaría; neutralizarla siempre dejaría pasar un
 * «toque» suelto sobre una fila firmada.
 *
 * **Sólo `UPDATE`, nunca `DELETE`**: el borrado en cascada desde `organizaciones`
 * tiene que seguir funcionando.
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
            CREATE OR REPLACE FUNCTION revision_direccion_inmutable() RETURNS trigger AS $$
            DECLARE
                comparable revisiones_direccion;
            BEGIN
                IF OLD.estado <> 'aprobada' THEN
                    RETURN NEW;
                END IF;

                comparable := NEW;
                comparable.estado := OLD.estado;

                IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                    comparable.updated_at := OLD.updated_at;
                END IF;

                IF comparable IS DISTINCT FROM OLD THEN
                    RAISE EXCEPTION
                        'Un acta de revision por la direccion aprobada no se modifica (revisiones_direccion %, codigo %)',
                        OLD.id, OLD.codigo;
                END IF;

                IF NEW.estado IS DISTINCT FROM OLD.estado
                   AND NEW.estado <> 'en_curso' THEN
                    RAISE EXCEPTION
                        'Una revision aprobada solo puede reabrirse a en_curso (revisiones_direccion %, codigo %: % -> %)',
                        OLD.id, OLD.codigo, OLD.estado, NEW.estado;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER revisiones_direccion_inmutables
                BEFORE UPDATE ON revisiones_direccion
                FOR EACH ROW EXECUTE FUNCTION revision_direccion_inmutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS revisiones_direccion_inmutables ON revisiones_direccion');
        DB::statement('DROP FUNCTION IF EXISTS revision_direccion_inmutable()');
    }
};
