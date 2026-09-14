<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una valoración de riesgo aceptada o jubilada no se modifica, y lo garantiza la
 * base. Hermana de `blindar_documento_versiones` y por el mismo motivo.
 *
 * Aceptar un riesgo es un acto formal con firma y fecha: es la organización
 * declarando que conoce una exposición y decide convivir con ella. Si esa fila se
 * puede reescribir desde PHP, no hay forma de demostrar qué se aceptó ni cuándo,
 * y una aceptación que no se puede demostrar no es una aceptación — es lo mismo
 * que pasaba con la versión de un documento entregado.
 *
 * Y una valoración jubilada —la que dejó de ser la vigente— es histórico. El
 * sentido de guardarla es poder comparar marzo con octubre; si se puede retocar
 * marzo, la comparación no vale nada.
 *
 * **La ventana editable es exactamente `vigente AND aceptada_en IS NULL`**, que es
 * el equivalente del borrador de un documento: mientras nadie la haya firmado y
 * siga siendo la que cuenta, corregirla es lo que se espera.
 *
 * Con una excepción que hay que dejar pasar sí o sí: **apagar `vigente`**. Jubilar
 * la anterior es el primer paso de toda reevaluación, y una valoración aceptada
 * hace un año es justo la que hay que jubilar al volver a mirar el riesgo. Si el
 * trigger lo bloqueara, un riesgo aceptado no se podría revaluar nunca — que es lo
 * contrario de lo que pide «reevaluación periódica».
 *
 * **Sólo `UPDATE`, nunca `DELETE`**, como en documentos: el borrado en cascada
 * desde `organizaciones` y desde `riesgos` tiene que seguir funcionando.
 *
 * `CREATE OR REPLACE` y no `CREATE` a secas: `migrate:fresh` tira las TABLAS y no
 * las funciones, así que la función sobrevive a un refresco y la segunda pasada
 * chocaría con ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION riesgo_valoracion_inmutable() RETURNS trigger AS $$
            DECLARE
                comparable riesgo_valoraciones;
            BEGIN
                -- Mientras sea la vigente y nadie la haya firmado, corregirla es
                -- lo que se espera: es el equivalente del borrador de un
                -- documento.
                IF OLD.vigente AND OLD.aceptada_en IS NULL THEN
                    RETURN NEW;
                END IF;

                /*
                 * A partir de ahí sólo se admite UN cambio: apagar `vigente`.
                 * Y hay que admitirlo, porque reevaluar al año siguiente exige
                 * jubilar la anterior, esté aceptada o no. Todo lo demás es
                 * reescribir lo que alguien firmó.
                 *
                 * Se compara el registro entero con `vigente` neutralizado, en
                 * vez de enumerar columnas: una columna nueva quedaría fuera de
                 * la lista y sería editable sin que nadie lo notara.
                 */
                comparable := NEW;
                comparable.vigente := OLD.vigente;

                IF comparable IS DISTINCT FROM OLD THEN
                    IF OLD.aceptada_en IS NOT NULL THEN
                        RAISE EXCEPTION
                            'Una valoracion aceptada no se modifica (riesgo_valoracion %, aceptada el %)',
                            OLD.id, OLD.aceptada_en;
                    END IF;

                    RAISE EXCEPTION
                        'Una valoracion jubilada es historico y no se modifica (riesgo_valoracion %)',
                        OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER riesgo_valoraciones_inmutables
                BEFORE UPDATE ON riesgo_valoraciones
                FOR EACH ROW EXECUTE FUNCTION riesgo_valoracion_inmutable()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS riesgo_valoraciones_inmutables ON riesgo_valoraciones');
        DB::statement('DROP FUNCTION IF EXISTS riesgo_valoracion_inmutable()');
    }
};
