<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entra el informe de auditoría interna, el séptimo documento **calculado**: § 4.18
 * y la cláusula 9.2.2, que pide conservar información documentada de los
 * resultados de cada auditoría.
 *
 * **Calcada de `2026_09_26_090000_ampliar_tipos_con_declaracion_de_conformidad.php`**
 * en los tres `CHECK` de tipo, escritos a mano en `up()` y en `down()` por la
 * regla de `.ai/rules/migraciones.md`: construidos desde `TipoDocumento::cases()`,
 * una base recién migrada dejaría pasar el valor nuevo aunque esta migración
 * faltara.
 *
 * ### Y trae una columna que ningún tipo anterior necesitó: `auditoria_id`
 *
 * El acta imprime **la última** revisión aprobada, y la Declaración de
 * Conformidad **la** conformidad viva del sistema: las dos son series con una
 * sola fuente viva. Un informe de auditoría no: cada auditoría tiene el suyo, y
 * un sistema con dos auditorías internas cerradas tiene dos informes, no dos
 * versiones del mismo. La fuente se tiene que poder **nombrar**.
 *
 * **El vínculo va en `documentos` y no en `auditorias`**, y no por gusto: una
 * auditoría cerrada es inmutable —su trigger compara la fila entera—, y el
 * informe se prepara precisamente después de cerrarla. Un `documento_id` en
 * `auditorias` no se podría escribir nunca en el único momento en que tiene
 * sentido escribirlo.
 *
 * - **Índice único parcial**: un informe por auditoría. Dos series para la misma
 *   auditoría serían dos documentos que pueden contar cosas distintas del mismo
 *   hecho.
 * - **`CHECK` en las dos direcciones**: un informe sin auditoría es un documento
 *   imposible, y una auditoría colgada de una SoA sería un dato que nadie lee.
 * - **`ON DELETE NO ACTION`**, que es el que pone `constrained()` por defecto, y
 *   no `RESTRICT`: `NO ACTION` se comprueba al final de la sentencia, así que
 *   borrar un sistema —que arrastra en cascada a la vez sus auditorías y sus
 *   documentos— sigue funcionando. Con `RESTRICT` la comprobación sería
 *   inmediata y el borrado en cascada moriría a medias. Borrar a mano una
 *   auditoría con informe sí falla, que es lo que se quiere: lo impide antes el
 *   controlador, con un mensaje que se lee.
 *
 * La RLS de `documentos` ya cubre la columna: es la fila entera la que se filtra.
 *
 * El `down()` borra por `ContextoOrganizacion::comoMantenimiento()`: sin él, RLS
 * deniega por defecto, los `DELETE` afectan a cero filas sin fallar y el `ALTER
 * TABLE` muere con «is violated by some row».
 */
return new class extends Migration
{
    /** Los diez tipos que había antes de éste. */
    private const ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad', "
        ."'declaracion_conformidad_ens'";

    /** Los once, con `informe_auditoria` dentro. */
    private const NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', "
        ."'acta_revision', 'politica', 'norma', 'procedimiento', 'plan_continuidad', "
        ."'declaracion_conformidad_ens', 'informe_auditoria'";

    /** Los que exigen sistema antes de éste. */
    private const CON_SISTEMA_ANTIGUOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'declaracion_conformidad_ens'";

    /** Y después: una auditoría es siempre de un sistema, y su informe también. */
    private const CON_SISTEMA_NUEVOS = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'declaracion_conformidad_ens', "
        ."'informe_auditoria'";

    public function up(): void
    {
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::NUEVOS.'))');

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('.self::CON_SISTEMA_NUEVOS.'))');

        Schema::table('documentos', function (Blueprint $table): void {
            $table->foreignId('auditoria_id')->nullable()->after('sistema_id')->constrained('auditorias');
        });

        DB::statement('CREATE UNIQUE INDEX documentos_auditoria_unica ON documentos (auditoria_id) WHERE auditoria_id IS NOT NULL');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_auditoria_check CHECK ((tipo = 'informe_auditoria') = (auditoria_id IS NOT NULL))");
    }

    public function down(): void
    {
        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::table('documento_plantilla_secciones')->where('tipo', 'informe_auditoria')->delete();
            DB::table('documentos')->where('tipo', 'informe_auditoria')->delete();
        });

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_auditoria_check');
        DB::statement('DROP INDEX documentos_auditoria_unica');

        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('auditoria_id');
        });

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('.self::CON_SISTEMA_ANTIGUOS.'))');

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ('.self::ANTIGUOS.'))');
    }
};
