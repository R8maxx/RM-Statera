<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El blindaje de `documento_versiones`, con la única puerta que el flujo de
 * aprobación necesita: **jubilar la versión aprobada anterior**.
 *
 * Hasta aquí la regla era simple —con número, la fila no se toca— y seguía
 * bastando, porque emitir era el último acto de la vida de una versión. Ya no lo
 * es: cuando se aprueba la v4, la v3 tiene que pasar a `obsoleto`, y sin esta
 * excepción un documento aprobado **no podría revisarse nunca**. Es exactamente
 * el mismo agujero que se le talló a `riesgo_valoraciones` para poder jubilar la
 * valoración anterior al reevaluar un riesgo, y se resuelve igual.
 *
 * **Se compara el registro entero con las tres columnas neutralizadas**, en vez
 * de enumerar qué se puede cambiar: una columna nueva quedaría fuera de la lista
 * y sería editable sin que nadie lo notara. Lo que no case, no pasa.
 *
 * `updated_at` entra en la neutralización porque Eloquent lo toca en cada
 * `save()`; en riesgos no hizo falta porque allí la escritura cae dentro de la
 * ventana editable. Sin él, jubilar una versión fallaría con un error que habla
 * de inmutabilidad y no de la marca de tiempo, que es de los que cuestan una
 * tarde.
 *
 * **Sólo `UPDATE`, nunca `DELETE`**: el borrado en cascada desde `organizaciones`
 * tiene que seguir funcionando. El objeto almacenado es el que no se toca, y de
 * eso responde el Object Lock del bucket.
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
            CREATE OR REPLACE FUNCTION documento_version_inmutable() RETURNS trigger AS $$
            DECLARE
                comparable documento_versiones;
            BEGIN
                -- El borrador se regenera cuantas veces haga falta: es
                -- precisamente lo que se espera de él. Y mientras no tenga
                -- número tampoco se ha entregado nada, así que redactarlo,
                -- mandarlo a revisión, rechazarlo o firmarlo entran aquí.
                IF OLD.numero IS NULL THEN
                    RETURN NEW;
                END IF;

                /*
                 * Con número, un solo cambio admitido: pasar de `aprobado` a
                 * `obsoleto` al aprobarse la siguiente. Todo lo demás es
                 * reescribir lo que alguien firmó y entregó.
                 */
                comparable := NEW;
                comparable.estado := OLD.estado;
                comparable.obsoleta_en := OLD.obsoleta_en;

                /*
                 * `updated_at` se neutraliza SÓLO cuando el estado cambia. Es la
                 * marca de tiempo de esa misma jubilación, y sin esto la
                 * escritura legítima fallaría. Neutralizarla siempre dejaría
                 * pasar un «toque» suelto sobre una fila entregada, y una fila
                 * que se puede tocar es una fila que alguien acabará tocando.
                 */
                IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                    comparable.updated_at := OLD.updated_at;
                END IF;

                IF comparable IS DISTINCT FROM OLD THEN
                    RAISE EXCEPTION
                        'Una version emitida no se modifica (documento_version %, v%)',
                        OLD.id, OLD.numero;
                END IF;

                IF NEW.estado IS DISTINCT FROM OLD.estado
                   AND NOT (OLD.estado = 'aprobado' AND NEW.estado = 'obsoleto') THEN
                    RAISE EXCEPTION
                        'Una version emitida solo puede pasar de aprobada a obsoleta (documento_version %, v%: % -> %)',
                        OLD.id, OLD.numero, OLD.estado, NEW.estado;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        // Se vuelve a la versión anterior de la función: con número, nada se
        // toca. El trigger que la invoca lo creó
        // `blindar_documento_versiones` y sigue en su sitio.
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
    }
};
