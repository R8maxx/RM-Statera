<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra el informe de estado, el octavo documento **calculado** y el último de los
 * que nombra el § 4.18.
 *
 * **Calcada de `2026_09_27_090100_ampliar_tipos_con_informe_de_auditoria.php`** en
 * los dos `CHECK` de tipo, escritos a mano en `up()` y en `down()` por la regla de
 * `.ai/rules/migraciones.md`.
 *
 * **`documentos_sistema_check` no se toca**: el informe de estado es de la
 * organización entera, como el análisis del contexto y el acta. Cuenta todos sus
 * sistemas a la vez, y colgarlo de uno sería decir que el resto no existe.
 *
 * El `down()` borra por `ContextoOrganizacion::comoMantenimiento()`: sin él, RLS
 * deniega por defecto, los `DELETE` afectan a cero filas sin fallar y el `ALTER
 * TABLE` muere con «is violated by some row».
 */
return new class extends Migration
{
    /** Los once tipos que había antes de éste. */
    private const ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad', "
        ."'declaracion_conformidad_ens', 'informe_auditoria'";

    /** Los doce, con `informe_estado` dentro. */
    private const NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad', "
        ."'declaracion_conformidad_ens', 'informe_auditoria', 'informe_estado'";

    public function up(): void
    {
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');
    }

    public function down(): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::table('documento_plantilla_secciones')->where('tipo', 'informe_estado')->delete();
            DB::table('documentos')->where('tipo', 'informe_estado')->delete();
        });

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');
    }
};
