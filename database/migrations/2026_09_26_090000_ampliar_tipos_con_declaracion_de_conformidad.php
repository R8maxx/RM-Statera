<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra la Declaración de Conformidad del ENS, el sexto documento **calculado**:
 * § 4.17, categoría básica.
 *
 * **Calcada de `2026_09_25_090200_ampliar_tipos_con_plan_de_continuidad.php`**, con
 * los `CHECK` escritos a mano en `up()` y en `down()` por la regla de
 * `.ai/rules/migraciones.md`: construidos desde `TipoDocumento::cases()`, una base
 * recién migrada dejaría pasar el valor nuevo aunque esta migración faltara.
 *
 * **Y aquí sí se toca `documentos_sistema_check`**, que las dos últimas no
 * tocaron. Una Declaración de Conformidad sin sistema no es un documento raro, es
 * un documento imposible: lo que se declara conforme es un sistema de información
 * concreto, con su categoría. `TipoDocumento::DeclaracionConformidadEns
 * ::exigeSistema()` devuelve `true`, y el `CHECK` tiene que decir lo mismo.
 *
 * El `down()` borra por `ContextoOrganizacion::comoMantenimiento()`: sin él, RLS
 * deniega por defecto, los `DELETE` afectan a cero filas sin fallar y el `ALTER
 * TABLE` muere con «is violated by some row». Es el fallo que ya mordió a cuatro
 * migraciones verificadas sobre una base vacía.
 */
return new class extends Migration
{
    /** Los nueve tipos que había antes de éste. */
    private const ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad'";

    /** Los diez, con `declaracion_conformidad_ens` dentro. */
    private const NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad', "
        ."'declaracion_conformidad_ens'";

    /** Los que exigen sistema antes de éste. */
    private const CON_SISTEMA_ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens'";

    /** Y después. */
    private const CON_SISTEMA_NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'declaracion_conformidad_ens'";

    public function up(): void
    {
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('.self::CON_SISTEMA_NUEVOS.'))');
    }

    public function down(): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::table('documento_plantilla_secciones')->where('tipo', 'declaracion_conformidad_ens')->delete();
            DB::table('documentos')->where('tipo', 'declaracion_conformidad_ens')->delete();
        });

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('.self::CON_SISTEMA_ANTIGUOS.'))');

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');
    }
};
