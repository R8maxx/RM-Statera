<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una auditoría cerrada no se reescribe, y lo garantiza la base.
 *
 * Tercer trigger de inmutabilidad del producto, hermano de
 * `blindar_documento_versiones` y `blindar_riesgo_valoraciones`, y por el mismo
 * motivo: una auditoría cerrada es un hecho que se le enseña al auditor de la
 * siguiente. Si sus puntos se pudieran cambiar desde PHP, bastaría con pasar un
 * `no_conforme` a `conforme` y borrar el hallazgo para que la auditoría del año
 * pasado dijera otra cosa — y no habría forma de demostrar qué se encontró.
 *
 * **La ventana editable es `estado <> 'cerrada'`.** Mientras está planificada o en
 * curso, corregir un punto es exactamente lo que se espera; es el equivalente del
 * borrador de un documento y de la valoración vigente sin firmar.
 *
 * **La puerta**, que los tres triggers tienen y que hace falta sí o sí: en
 * `auditorias` se admite el paso de `cerrada` a `en_curso` —y sólo eso— para poder
 * **reabrir**. Un error material en una auditoría cerrada tiene que poder
 * corregirse; lo que no puede es corregirse a escondidas, y por eso reabrir es un
 * cambio de estado con su fecha y su autor, no una edición silenciosa. Sin esta
 * puerta, una auditoría mal cerrada se queda mal para siempre — que es lo mismo
 * que pasaría con un riesgo aceptado que no se pudiera revaluar.
 *
 * Se compara el registro entero con las columnas del propio cierre neutralizadas,
 * en vez de enumerar las que se pueden tocar: una columna nueva quedaría fuera de
 * la lista y sería editable sin que nadie lo notara. Es lo que ya se hizo con
 * `vigente` en `riesgo_valoraciones` y con `estado`/`obsoleta_en` en
 * `documento_versiones`.
 *
 * **Sólo `UPDATE`, nunca `DELETE`**, como en los otros dos: el borrado en cascada
 * desde `organizaciones` y desde `sistemas` tiene que seguir funcionando.
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
            CREATE OR REPLACE FUNCTION auditoria_inmutable() RETURNS trigger AS $$
            DECLARE
                comparable auditorias;
            BEGIN
                -- Mientras no esté cerrada, es trabajo en curso.
                IF OLD.estado <> 'cerrada' THEN
                    RETURN NEW;
                END IF;

                /*
                 * Cerrada, sólo se admite reabrir: volver a `en_curso` soltando
                 * la fecha y el firmante del cierre. Es la puerta, y deja rastro
                 * porque es una transición y no una edición.
                 */
                comparable := NEW;
                comparable.estado := OLD.estado;
                comparable.fecha_cierre := OLD.fecha_cierre;
                comparable.cerrada_por_id := OLD.cerrada_por_id;
                comparable.updated_at := OLD.updated_at;

                IF comparable IS DISTINCT FROM OLD THEN
                    RAISE EXCEPTION
                        'Una auditoria cerrada no se modifica (auditoria %, cerrada el %). Reabrela primero.',
                        OLD.id, OLD.fecha_cierre;
                END IF;

                -- Y el único destino válido es `en_curso`: de cerrada no se vuelve
                -- a «planificada», que sería decir que nunca se hizo.
                IF NEW.estado NOT IN ('cerrada', 'en_curso') THEN
                    RAISE EXCEPTION
                        'Una auditoria cerrada solo puede reabrirse a en_curso (auditoria %)', OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER auditorias_inmutables
                BEFORE UPDATE ON auditorias
                FOR EACH ROW EXECUTE FUNCTION auditoria_inmutable()
        SQL);

        /*
         * Los puntos y los hallazgos no tienen estado propio: el que manda es el
         * de su auditoría. De ahí la subconsulta — es el precio de que la
         * inmutabilidad viva donde vive la decisión de cerrar, y no duplicada en
         * cada fila de detalle.
         *
         * **Consecuencia para quien cierre una auditoría: primero se congelan los
         * puntos y después se marca `cerrada`.** Al revés, el trigger bloquea el
         * propio congelado con un error que habla de la checklist y no del orden.
         * `CerrarAuditoria` lo hace en ese orden y lo dice.
         */
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

        foreach (['auditoria_puntos', 'hallazgos'] as $tabla) {
            /*
             * Aquí sí entra `DELETE`, a diferencia del trigger de la auditoría:
             * borrar un hallazgo de una auditoría cerrada es precisamente el
             * gesto contra el que existe esto.
             *
             * **El borrado en cascada SÍ pasa por aquí**, y este comentario decía
             * lo contrario. PostgreSQL ejecuta el `DELETE` sobre las filas hijas
             * y sus triggers de fila se disparan; quien lo deja pasar es que la
             * auditoría ya no está y la comprobación no encuentra fila. Lo
             * arregla `2026_09_17_090300_arreglar_blindaje_en_borrado`, que
             * además corrige que en un `DELETE` no existe `NEW`.
             */
            DB::statement(<<<SQL
                CREATE TRIGGER {$tabla}_inmutables
                    BEFORE INSERT OR UPDATE OR DELETE ON {$tabla}
                    FOR EACH ROW EXECUTE FUNCTION detalle_de_auditoria_inmutable()
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['auditoria_puntos', 'hallazgos'] as $tabla) {
            DB::statement("DROP TRIGGER IF EXISTS {$tabla}_inmutables ON {$tabla}");
        }

        DB::statement('DROP FUNCTION IF EXISTS detalle_de_auditoria_inmutable()');
        DB::statement('DROP TRIGGER IF EXISTS auditorias_inmutables ON auditorias');
        DB::statement('DROP FUNCTION IF EXISTS auditoria_inmutable()');
    }
};
