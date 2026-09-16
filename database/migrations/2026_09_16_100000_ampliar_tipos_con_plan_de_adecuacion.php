<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra el plan de adecuación del ENS, el tercer documento calculado.
 *
 * Con él se cierra la fase 2 de la especificación —«el papel formal»—, que pedía
 * riesgos con metodología, documentos con flujo de aprobación y **SoA, DdA y plan
 * de adecuación**.
 *
 * Los `CHECK` de tipo se rehacen enumerando `TipoDocumento::cases()`, igual que
 * hizo la migración de los documentos redactados. Eso evita el error de copia,
 * **no el de olvido**: una migración ya ejecutada no se vuelve a ejecutar, así
 * que el siguiente tipo de documento necesitará otra vez la suya.
 *
 * **Y aquí sí se toca `documentos_sistema_check`**, que la migración anterior
 * dejó escrito expresamente que no se tocara. Su razón sigue siendo buena y por
 * eso conviene decir por qué deja de aplicar: estaba redactado en negativo
 * —`sistema_id IS NOT NULL OR tipo NOT IN ('soa_iso', 'dda_ens')`— para que un
 * tipo **de ámbito organizativo** no obligara a reescribirlo, y los tres que
 * llegaron después —política, norma y procedimiento— lo eran. El plan de
 * adecuación es el primer tipo **calculado** que llega detrás, y un plan de
 * adecuación sin sistema no es un documento raro, es un documento imposible: sus
 * medidas salen de la categorización de un sistema concreto. Con el `CHECK`
 * antiguo, la base lo habría dejado pasar y la única barrera sería el
 * `FormRequest`.
 *
 * La lista se construye desde el enum filtrando los que no son redactados, para
 * que el cuarto documento calculado no tenga que acordarse de escribir su valor
 * a mano en dos sitios.
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
            static fn (TipoDocumento $tipo): bool => ! $tipo->esRedactado(),
        ));

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ({$conSistema}))");
    }

    public function down(): void
    {
        $antiguos = "'soa_iso', 'dda_ens', 'politica', 'norma', 'procedimiento'";

        // Las filas del tipo nuevo se van antes que el CHECK que vuelve a
        // prohibirlas; si no, el `ALTER TABLE` no valida y la migración se queda
        // a medias.
        DB::table('documento_plantilla_secciones')->where('tipo', 'plan_adecuacion_ens')->delete();
        DB::table('documentos')->where('tipo', 'plan_adecuacion_ens')->delete();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$antiguos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$antiguos}))");

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_sistema_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('soa_iso', 'dda_ens'))");
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
