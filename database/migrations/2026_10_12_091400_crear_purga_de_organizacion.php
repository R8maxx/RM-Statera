<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La única puerta para borrar de verdad a un cliente (punto 57).
 *
 * **Lo que destapó la prueba previa.** Un `DELETE` sobre `organizaciones`
 * arrastra en cascada todo lo del cliente, y PostgreSQL ejecuta las cascadas
 * con los privilegios del dueño de la tabla: el `REVOKE DELETE` de
 * `eventos_auditoria` no las frena y RLS no las filtra. Así que la aplicación,
 * con un solo `DELETE`, podía borrar una organización entera, traza
 * «inmutable» incluida, sin plazo ni control. Ningún código lo hacía; nada lo
 * impedía.
 *
 * Desde aquí:
 *
 * 1. **`statera_app` pierde el `DELETE` sobre `organizaciones`.**
 * 2. **`purgar_organizacion()` es la puerta**, `SECURITY DEFINER` y del
 *    migrador, con `search_path` fijado. Lo que **no** puede hacer es lo que
 *    importa: no borra una organización que no lleve de baja al menos
 *    **noventa días**, y el plazo está escrito aquí, no lo pasa quien llama.
 *    Además limpia lo que la cascada no alcanza —los roles de spatie de ese
 *    «team», sin clave foránea— y suprime las cuentas de cliente, que se
 *    quedarían huérfanas. A quien administra la plataforma y era miembro sólo
 *    lo desvincula.
 *
 * Es la misma vara que `depurar_traza_de_persona()`: cualquier otra puerta así
 * se escribe con estas restricciones o no se escribe (`aislamiento.md`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('REVOKE DELETE ON organizaciones FROM statera_app');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION purgar_organizacion(p_organizacion bigint)
            RETURNS jsonb
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            DECLARE
                v_baja timestamptz;
                v_cuentas bigint[];
                v_desvinculadas integer;
            BEGIN
                SELECT baja_en INTO v_baja FROM organizaciones WHERE id = p_organizacion FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'La organizacion % no existe.', p_organizacion;
                END IF;

                IF v_baja IS NULL OR v_baja > now() - interval '90 days' THEN
                    RAISE EXCEPTION 'La organizacion % no lleva noventa dias de baja: no se purga.', p_organizacion;
                END IF;

                SELECT coalesce(array_agg(id), '{}') INTO v_cuentas
                    FROM users WHERE organizacion_id = p_organizacion AND es_plataforma = false;

                UPDATE users SET organizacion_id = NULL
                    WHERE organizacion_id = p_organizacion AND es_plataforma = true;
                GET DIAGNOSTICS v_desvinculadas = ROW_COUNT;

                DELETE FROM model_has_roles WHERE organizacion_id = p_organizacion;
                DELETE FROM roles WHERE organizacion_id = p_organizacion;

                DELETE FROM organizaciones WHERE id = p_organizacion;

                DELETE FROM sessions WHERE user_id = ANY (v_cuentas);
                DELETE FROM users WHERE id = ANY (v_cuentas);

                RETURN jsonb_build_object(
                    'cuentas_suprimidas', coalesce(array_length(v_cuentas, 1), 0),
                    'administradores_desvinculados', v_desvinculadas
                );
            END;
            $$
        SQL);

        DB::statement('REVOKE ALL ON FUNCTION purgar_organizacion(bigint) FROM PUBLIC');
        DB::statement('GRANT EXECUTE ON FUNCTION purgar_organizacion(bigint) TO statera_app');
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS purgar_organizacion(bigint)');
        DB::statement('GRANT DELETE ON organizaciones TO statera_app');
    }
};
