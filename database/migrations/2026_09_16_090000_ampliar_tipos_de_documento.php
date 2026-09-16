<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entran los documentos redactados: política, norma y procedimiento.
 *
 * Hasta aquí Statera sólo sabía **calcular** documentos —las dos declaraciones de
 * aplicabilidad son dos consultas sobre `implantaciones`— y el § 4.5 pide además
 * la jerarquía «política → normas → procedimientos». Son documentos que escribe
 * la organización, y son los que dan sentido al acuse de lectura: nadie acusa
 * recibo de una Declaración de Aplicabilidad, y todo el mundo tiene que acusar
 * recibo de la política de seguridad.
 *
 * **Va en su propia migración a propósito.** Ampliar un `CHECK` y añadir un flujo
 * de aprobación son dos cosas, y quien revise la entrega tiene que poder ver de
 * un vistazo cuál es cuál — el mismo criterio por el que las políticas de RLS
 * viven separadas del esquema que las necesita.
 *
 * Los `CHECK` se rehacen enumerando `TipoDocumento::cases()`, igual que los creó
 * `create_narrativa_documentos_tables`. Eso evita el error de copia, **no el de
 * olvido**: una migración ya ejecutada no se vuelve a ejecutar, así que el
 * siguiente tipo de documento necesitará otra vez su propia migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipos = $this->lista();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$tipos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$tipos}))");

        /*
         * `documentos_sistema_check` NO se toca, y conviene dejarlo escrito para
         * que nadie lo «arregle»: está redactado en negativo
         * —`sistema_id IS NOT NULL OR tipo NOT IN ('soa_iso', 'dda_ens')`—
         * precisamente para que un tipo de ámbito organizativo no obligue a
         * reescribirlo. Una política sin sistema ya pasa.
         *
         * `documento_secciones` tampoco: no lleva columna `tipo`, porque es
         * derivable de `documentos.tipo` y duplicarla abre la puerta a que
         * diverjan.
         */
    }

    public function down(): void
    {
        $antiguos = "'soa_iso', 'dda_ens'";

        // Las filas de los tipos nuevos se van antes que el CHECK que las
        // prohíbe otra vez; si no, el `ALTER TABLE` no valida y la migración se
        // queda a medias.
        DB::table('documento_plantilla_secciones')->whereNotIn('tipo', ['soa_iso', 'dda_ens'])->delete();
        DB::table('documentos')->whereNotIn('tipo', ['soa_iso', 'dda_ens'])->delete();

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT documentos_tipo_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ({$antiguos}))");

        DB::statement('ALTER TABLE documento_plantilla_secciones DROP CONSTRAINT plantilla_secciones_tipo_check');
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$antiguos}))");
    }

    /** Los valores del enum, como lista SQL entrecomillada. */
    private function lista(): string
    {
        return implode(', ', array_map(
            static fn (TipoDocumento $tipo): string => "'".$tipo->value."'",
            TipoDocumento::cases(),
        ));
    }
};
