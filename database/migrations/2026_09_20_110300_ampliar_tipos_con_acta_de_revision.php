<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra el acta de revisión por la dirección, el **quinto documento calculado**.
 *
 * **Es el quinto tipo, y la migración anterior lo anunció por escrito**: «los dos
 * `CHECK` de tipo se siguen construyendo desde `cases()`, que evita el error de
 * copia; no evita el de olvido: una migración ya ejecutada no se repite, así que
 * el quinto tipo de documento necesitará la suya». Ésta es esa migración.
 *
 * **`documentos_sistema_check` NO se toca esta vez**, y eso es una noticia: la
 * migración anterior lo rehízo por tercera vez para cambiar su motor de
 * `! esRedactado()` a `exigeSistema()`, y aquel cambio era justamente para que un
 * tipo nuevo de ámbito organizativo no obligara a rehacerlo otra vez. El acta lo
 * es —lo que la dirección revisa es el SGSI entero, no un sistema—, así que
 * `exigeSistema()` devuelve `false` y el `CHECK` sigue valiendo tal cual. La
 * decisión de aquella migración se paga aquí.
 *
 * **Y el `CHECK` de tipo no lo prueba `migrate:fresh`**: se construye desde el
 * enum en ejecución, así que sobre una base recién migrada ya incluye el valor
 * nuevo aunque falte esta migración y ningún test se pondría rojo. Hay que correr
 * `migrate` sobre una base existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipos = $this->lista(TipoDocumento::cases());

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$tipos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$tipos}))");
    }

    public function down(): void
    {
        $antiguos = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'analisis_contexto', 'politica', 'norma', 'procedimiento'";

        // Las filas del tipo nuevo se van antes que el CHECK que vuelve a
        // prohibirlas; si no, el `ALTER TABLE` no valida y la migración se queda
        // a medias.
        DB::table('documento_plantilla_secciones')->where('tipo', 'acta_revision')->delete();
        DB::table('documentos')->where('tipo', 'acta_revision')->delete();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$antiguos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$antiguos}))");
    }

    /**
     * Los valores, como lista SQL entrecomillada.
     *
     * @param  iterable<TipoDocumento>  $tipos
     */
    private function lista(iterable $tipos): string
    {
        $valores = [];

        foreach ($tipos as $tipo) {
            $valores[] = "'".$tipo->value."'";
        }

        return implode(', ', $valores);
    }
};
