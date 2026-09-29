<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La única puerta para modificar la traza, y lo estrecha que es (punto 36).
 *
 * La traza no se modifica: `statera_app` no tiene `UPDATE` sobre
 * `eventos_auditoria` y, desde el punto 32, tampoco puede devolvérselo. Pero el
 * derecho de supresión del RGPD (art. 17) choca con eso de frente: los eventos
 * de una persona guardan su nombre, su correo y sus notas, y «los datos siguen
 * en el log» no es una supresión.
 *
 * Esta función es el término medio, y lo que la hace aceptable es **lo que no
 * puede hacer**:
 *
 * - **Sólo quita claves, no cambia valores ni borra eventos.** El evento sigue
 *   diciendo que alguien dio de alta a una persona el 3 de marzo, y quién. Lo que
 *   desaparece es qué ponía en su ficha.
 * - **Sólo claves de una lista fija**, escrita aquí y no recibida por
 *   parámetro. Quien la llame no puede usarla para limpiar otro campo.
 * - **Sólo eventos de esa persona y de sus adjuntos**, y sólo de la
 *   organización fijada en la sesión, además de RLS.
 *
 * Es `SECURITY DEFINER` y es del migrador, que es el dueño de la tabla: así la
 * aplicación puede ejecutarla sin tener `UPDATE`. `search_path` va fijado,
 * porque una función de ésas con el `search_path` del llamador se secuestra
 * creando una tabla con el mismo nombre en otro esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION depurar_traza_de_persona(p_persona bigint, p_adjuntos bigint[])
            RETURNS integer
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            DECLARE
                organizacion bigint := nullif(current_setting('app.organizacion_actual', true), '')::bigint;
                claves_persona text[] := ARRAY[
                    'nombre_pila', 'apellido1', 'apellido2', 'nombre', 'nif', 'nif_huella',
                    'telefono', 'telefono_fijo', 'direccion', 'fecha_nacimiento', 'email',
                    'notas', 'user_id'
                ];
                claves_adjunto text[] := ARRAY['titulo', 'nota', 'nombre_fichero'];
                afectados integer;
            BEGIN
                IF organizacion IS NULL THEN
                    RAISE EXCEPTION 'Sin organizacion en la sesion no se depura ninguna traza';
                END IF;

                UPDATE eventos_auditoria
                SET valor_anterior = valor_anterior - claves_persona,
                    valor_nuevo = valor_nuevo - claves_persona
                WHERE organizacion_id = organizacion
                  AND entidad = 'Persona'
                  AND entidad_id = p_persona;

                GET DIAGNOSTICS afectados = ROW_COUNT;

                UPDATE eventos_auditoria
                SET valor_anterior = valor_anterior - claves_adjunto,
                    valor_nuevo = valor_nuevo - claves_adjunto
                WHERE organizacion_id = organizacion
                  AND entidad = 'Adjunto'
                  AND entidad_id = ANY (p_adjuntos);

                RETURN afectados;
            END;
            $$
        SQL);

        DB::statement('REVOKE ALL ON FUNCTION depurar_traza_de_persona(bigint, bigint[]) FROM PUBLIC');
        DB::statement('GRANT EXECUTE ON FUNCTION depurar_traza_de_persona(bigint, bigint[]) TO statera_app');
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS depurar_traza_de_persona(bigint, bigint[])');
    }
};
