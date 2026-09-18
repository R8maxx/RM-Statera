<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra el análisis del contexto, el cuarto documento **calculado**.
 *
 * **Y con él se rehace `documentos_sistema_check` por tercera vez.** La migración
 * anterior lo dejó construido desde `! esRedactado()` y explicó por qué: los tres
 * calculados que había eran de un sistema y los tres redactados de la
 * organización, así que una sola frontera servía para las dos preguntas.
 *
 * Ese accidente se acaba aquí. El análisis del contexto **es calculado** —sale de
 * una consulta sobre el DAFO y las partes interesadas, congelada al aprobar la
 * revisión— y **no cuelga de un sistema**, porque las cuestiones internas y
 * externas son de la organización entera. Con el `CHECK` anterior, crear uno
 * fallaría con un error de restricción que hablaría de `sistema_id` sin decir por
 * qué.
 *
 * Así que el motor del `CHECK` pasa a ser `TipoDocumento::exigeSistema()`, que es
 * una pregunta distinta de `esRedactado()` y ahora se escribe como tal. Los dos
 * `CHECK` de tipo se siguen construyendo desde `cases()`, que evita el error de
 * copia; **no evita el de olvido**: una migración ya ejecutada no se repite, así
 * que el quinto tipo de documento necesitará la suya.
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

        $conSistema = $this->lista(array_filter(
            TipoDocumento::cases(),
            static fn (TipoDocumento $tipo): bool => $tipo->exigeSistema(),
        ));

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ({$conSistema}))");
    }

    public function down(): void
    {
        $antiguos = "'soa_iso', 'dda_ens', 'plan_adecuacion_ens', 'politica', 'norma', 'procedimiento'";

        // Las filas del tipo nuevo se van antes que el CHECK que vuelve a
        // prohibirlas; si no, el `ALTER TABLE` no valida y la migración se queda
        // a medias.
        DB::table('documento_plantilla_secciones')->where('tipo', 'analisis_contexto')->delete();
        DB::table('documentos')->where('tipo', 'analisis_contexto')->delete();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$antiguos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$antiguos}))");

        // Se vuelve a la lista literal de los tres calculados de entonces, que es
        // lo que `! esRedactado()` devolvía antes de que existiera el cuarto.
        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('soa_iso', 'dda_ens', 'plan_adecuacion_ens'))");
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
