<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de obligaciones periódicas: la mitad del § 4.16 que no sale de
 * ningún registro.
 *
 * El calendario de obligaciones enumera once cosas periódicas, y seis de ellas
 * —el informe INES, la renovación de conformidad del ENS, la auditoría de
 * seguimiento de ISO, la auditoría interna, la reevaluación de riesgos y la
 * revisión por la dirección— **no tienen fecha de la que derivarse**. Lo que
 * vence no es una fila que exista: es una fila que debería existir y no está. Una
 * `Fuente` más del calendario no lo resuelve, porque no hay nada que consultar.
 *
 * **Tabla GLOBAL: no lleva `organizacion_id`, no lleva RLS y no debe llevarlos**
 * (invariante 2), igual que `amenazas`, `marcos` y `requisitos`. «Presentar el
 * informe INES una vez al año» no es un hecho de ninguna organización en
 * concreto. Lo que sí es de cada organización es haberlo asumido, y eso son
 * `compromisos`.
 *
 * Y va como datos y no como enum, por el invariante 3: el RD 311/2022 tendrá
 * revisiones y la periodicidad de la renovación de conformidad es justo el tipo
 * de cifra que cambia en un boletín. El día que llegue hay que poder verlo en un
 * diff, no en un `composer update`.
 *
 * **La periodicidad es un entero de meses y no un enum**, que es lo que ya hacen
 * `documentos.periodicidad_revision_meses` y
 * `metodologias_riesgo.periodicidad_revision_meses`. Un tercer enum de
 * periodicidad —ya hay dos, `Metrica\Enums\Periodicidad` y
 * `Evidencia\Enums\PeriodicidadRenovacion`— es justo lo que el docblock del
 * primero advierte. Y el entero expresa gratis la **bienal (24)** de la
 * conformidad del ENS, que ninguno de los dos enums sabe decir, y deja que una
 * organización se comprometa a dieciocho meses si quiere.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CATEGORIAS = ['basica', 'media', 'alta'];

    /**
     * Con qué registro del producto se demuestra que la obligación se cumplió.
     * Es una sugerencia del catálogo, no una restricción: el cumplimiento puede
     * apuntar a otro sitio o a ninguno.
     *
     * @var list<string>
     */
    private const REFERENCIAS = ['auditoria', 'revision_direccion', 'documento'];

    public function up(): void
    {
        Schema::create('obligaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique()->comment('ens.ines, ens.conformidad, iso.auditoria-interna');

            /*
             * De qué marco sale la obligación, y **no una bandera `es_del_ens`**.
             * Si apunta a `ENS-RD311-2022`, se propone a quien tenga un sistema
             * de ese marco; eso es un dato y no un literal en el código
             * (invariante 3), y es lo que permitirá que NIS2 traiga las suyas sin
             * tocar esta tabla.
             *
             * Nula es «de cualquier sistema de gestión»: la revisión por la
             * dirección la piden ISO y el ENS con palabras distintas y es la
             * misma reunión.
             */
            $table->foreignId('marco_id')->nullable()->constrained('marcos')->nullOnDelete();

            $table->string('nombre');
            $table->text('descripcion')->nullable();

            /*
             * De dónde sale la obligación, citado. Es lo que separa esta tabla de
             * una lista de buenas intenciones: «RD 311/2022, art. 38» se puede
             * comprobar, y va impreso en la ficha.
             */
            $table->string('base_legal')->nullable();

            $table->smallInteger('periodicidad_meses_sugerida');

            /*
             * A partir de qué categoría muerde. Las pruebas de continuidad no son
             * exigibles en básica, así que no se proponen hoy; el día que haya un
             * cliente de media aparecen solas y sin migración.
             *
             * Se compara contra `Sistema::categoria()`, que **se deriva**
             * (invariante 4). Aquí no se guarda ninguna copia de esa categoría.
             */
            $table->string('categoria_minima')->nullable();

            $table->string('referencia_sugerida')->nullable();

            $table->unsignedInteger('orden')->default(0);

            // Mismo contrato que requisitos y amenazas: huella del contenido para
            // distinguir lo modificado de lo intacto, y retirada por marca y nunca
            // por borrado, porque puede haber compromisos colgando y el auditor
            // preguntará por ellos.
            $table->string('huella', 64)->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamp('retirado_en')->nullable();

            $table->timestamps();

            $table->index(['vigente', 'orden']);
        });

        $categorias = $this->lista(self::CATEGORIAS);
        $referencias = $this->lista(self::REFERENCIAS);

        DB::statement("ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_categoria_check CHECK (categoria_minima IS NULL OR categoria_minima IN ({$categorias}))");
        DB::statement("ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_referencia_check CHECK (referencia_sugerida IS NULL OR referencia_sugerida IN ({$referencias}))");
        DB::statement('ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_nombre_check CHECK (length(trim(nombre)) > 0)');

        /*
         * De uno a ciento veinte meses. El suelo evita el cero, que dejaría un
         * compromiso venciendo el mismo día que se cumple; el techo son diez años,
         * y por encima de eso lo que hay es un error de tecleo, no una cadencia.
         */
        DB::statement('ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_periodicidad_check CHECK (periodicidad_meses_sugerida BETWEEN 1 AND 120)');
    }

    public function down(): void
    {
        Schema::dropIfExists('obligaciones');
    }

    /** @param list<string> $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
    }
};
