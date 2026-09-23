<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra el plan de continuidad, el cuarto documento **redactado**: § 4.11.
 *
 * **Calcada de `2026_09_20_110300_ampliar_tipos_con_acta_de_revision.php`**,
 * salvo en una cosa: aquélla construía los dos `CHECK` desde
 * `TipoDocumento::cases()` en tiempo de ejecución. Aquí van **escritos a
 * mano**, en `up()` y en `down()`, que es la regla de `.ai/rules/migraciones.md`
 * para todo `CHECK` construido desde un enum — «el `CHECK` construido desde un
 * enum que `migrate:fresh` no prueba» es justo el fallo silencioso que
 * `cases()` en tiempo de ejecución permitía: sobre una base recién migrada, el
 * `CHECK` ya incluye el valor nuevo aunque falte esta migración, y ningún test
 * se pone rojo. Con literales escritos a mano, `up()` sólo puede dejar pasar
 * `plan_continuidad` si esta migración se ha ejecutado de verdad.
 *
 * **`documentos_sistema_check` no se toca**, igual que con el acta de
 * revisión y por el mismo motivo: `TipoDocumento::PlanContinuidad
 * ::exigeSistema()` devuelve `false` —un plan de continuidad es de la
 * organización entera y puede cubrir servicios de varios sistemas a la vez—,
 * así que el `CHECK` en negativo sigue valiendo tal cual.
 */
return new class extends Migration
{
    /** Los ocho tipos que había antes de éste. */
    private const ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento'";

    /** Los nueve tipos, con `plan_continuidad` dentro. */
    private const NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad'";

    public function up(): void
    {
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');
    }

    public function down(): void
    {
        // Las filas del tipo nuevo se van antes que el CHECK que vuelve a
        // prohibirlas; si no, el `ALTER TABLE` no valida y la migración se
        // queda a medias.
        DB::table('documento_plantilla_secciones')->where('tipo', 'plan_continuidad')->delete();
        DB::table('documentos')->where('tipo', 'plan_continuidad')->delete();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');
    }
};
