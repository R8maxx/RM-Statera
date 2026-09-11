<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los textos que la organización redacta en sus documentos.
 *
 * Hasta ahora el documento se calculaba entero y **el usuario no podía escribir
 * ni un carácter que saliera en el PDF**: todo el aparato narrativo —títulos,
 * introducciones, los párrafos que explican cómo leer cada columna— era literal
 * en Blade o en PHP. Esto es el § 4.5 de la especificación, «plantillas base
 * personalizables por organización».
 *
 * **Lo que NO cambia**: la tabla de controles, la derivación de la categoría y
 * las cifras se siguen calculando desde `implantaciones`. Se edita el envoltorio,
 * no el dato. Una justificación se corrige en su requisito, que es donde vive.
 *
 * **Por qué no hay tabla padre de plantillas.** La plantilla ES el conjunto de
 * filas, y una fila que falta no significa «vacío»: significa «vale el texto que
 * Statera trae de fábrica». Así una organización nueva no necesita sembrado
 * ninguno, una mejora futura del texto llega sola a quien no lo haya tocado, y
 * —lo importante— **la cadena vacía es un valor con significado**: «aquí no va
 * nada, y lo he decidido yo». Por eso `contenido_md` es `NOT NULL` y se guarda
 * `''`, nunca `NULL`; sin esa distinción, borrar un texto lo resucitaría en la
 * siguiente generación, que es el fallo silencioso de siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        $secciones = $this->lista(array_map(
            static fn (SeccionNarrativa $s): string => $s->value,
            SeccionNarrativa::cases(),
        ));

        $tipos = $this->lista(array_map(
            static fn (TipoDocumento $t): string => $t->value,
            TipoDocumento::cases(),
        ));

        $origenes = $this->lista(array_map(
            static fn (OrigenTexto $o): string => $o->value,
            OrigenTexto::cases(),
        ));

        // --- La plantilla de la organización ---------------------------------

        Schema::create('documento_plantilla_secciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('tipo')->comment('soa_iso, dda_ens');
            $table->string('seccion')->comment('introduccion, metodologia, nota_tabla…');

            // Markdown, nunca HTML: es lo diffeable, lo que cabe en la
            // instantánea sin inflarla y lo que no tiene superficie de inyección.
            $table->text('contenido_md');

            $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organizacion_id', 'tipo', 'seccion']);
            $table->index(['organizacion_id', 'tipo']);
        });

        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_seccion_check CHECK (seccion IN ({$secciones}))");
        DB::statement('ALTER TABLE documento_plantilla_secciones ADD CONSTRAINT plantilla_secciones_longitud_check CHECK (length(contenido_md) <= 20000)');

        // --- La copia por documento ------------------------------------------

        Schema::create('documento_secciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();

            $table->string('seccion');
            $table->text('contenido_md');

            // Para poder decir en la interfaz «esto viene de la plantilla» y
            // ofrecer «Restablecer». Retocado no es un error: es información.
            $table->string('origen')->default(OrigenTexto::Plantilla->value);

            $table->timestamps();

            $table->unique(['documento_id', 'seccion']);
            $table->index(['organizacion_id', 'documento_id']);
        });

        DB::statement("ALTER TABLE documento_secciones ADD CONSTRAINT documento_secciones_seccion_check CHECK (seccion IN ({$secciones}))");
        DB::statement("ALTER TABLE documento_secciones ADD CONSTRAINT documento_secciones_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement('ALTER TABLE documento_secciones ADD CONSTRAINT documento_secciones_longitud_check CHECK (length(contenido_md) <= 20000)');

        /*
         * No lleva `tipo`: es derivable de `documentos.tipo` y duplicarlo abre la
         * puerta a que diverjan. La contrapartida es que el CHECK no puede
         * validar la coherencia sección↔tipo; de eso responden el `FormRequest`
         * —que construye sus reglas desde `SeccionNarrativa::paraTipo()`— y el
         * resolutor, que ignora las filas que no apliquen. Un trigger para esto
         * sería una función de PostgreSQL que hay que tocar cada vez que entre un
         * tipo de documento nuevo, y no compensa.
         */
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_secciones');
        Schema::dropIfExists('documento_plantilla_secciones');
    }

    /**
     * Los valores del enum, como lista SQL entrecomillada.
     *
     * Se lee del enum para no transcribir once cadenas a mano, que es como se
     * cuela una errata que sólo aparece el día que alguien usa esa sección.
     *
     * **Esto no mantiene el `CHECK` al día solo**: una migración ya ejecutada no
     * se vuelve a ejecutar, así que un hueco nuevo en el enum necesita su propia
     * migración que rehaga la restricción. Lo que se evita aquí es el error de
     * copia, no el de olvido.
     *
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(
            static fn (string $valor): string => "'".$valor."'",
            $valores,
        ));
    }
};
