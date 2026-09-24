<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La conformidad con el ENS de un sistema: § 4.17.
 *
 * Hasta aquí existía el primer tercio del flujo —la autoevaluación, como
 * `TipoAuditoria::Autoevaluacion`— y cerrarla no producía nada. Esta tabla es lo
 * que une los tres pasos de categoría básica: **autoevaluación cerrada →
 * Declaración de Conformidad firmada → distintivo publicado**.
 *
 * **Una fila por declaración y no una columna en `sistemas`**, por dos motivos.
 * El primero, el invariante 7: la conformidad se renueva cada dos años y el
 * auditor pregunta por la anterior, así que la del 2026 no puede sobrescribirse
 * con la del 2028. El segundo, el invariante 4: la categoría de un sistema se
 * deriva y no se guarda, pero **la declaración certifica la categoría del día en
 * que se inició**, y revalorar el sistema en octubre no puede cambiar lo que se
 * declaró en marzo. De ahí `categoria` congelada, con el mismo razonamiento que
 * `auditoria_puntos.exigencia_congelada`.
 *
 * **La vía de certificación se modela y no se implementa**, que es lo que pide
 * el § 4.17 para media y alta: la columna y sus dos datos —la entidad acreditada
 * por ENAC y el número de certificado— existen desde hoy, con su `CHECK`, para
 * que el día que se construya no haya que rehacer la tabla. Lo que no existe es
 * el camino que las rellena.
 *
 * **`caducada` no es un estado**: se deriva de `vigente_hasta`. Guardarlo
 * obligaría a un comando que lo marcara cada noche, y un comando que falla una
 * noche deja una declaración vencida enseñándose como vigente.
 *
 * **El distintivo se registra, no se sirve.** Lo publica la organización en su
 * web junto a la declaración (CCN-STIC 809); aquí queda dónde, desde cuándo y la
 * evidencia de que se hizo. Statera no abre ninguna ruta pública para enseñarlo.
 *
 * Los `CHECK` se construyen desde constantes de esta migración y no desde los
 * enums, como en `mejoras` y `auditorias`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['en_preparacion', 'declarada', 'publicada', 'retirada'];

    /** @var list<string> */
    private const VIAS = ['declaracion', 'certificacion'];

    /** @var list<string> */
    private const CATEGORIAS = ['basica', 'media', 'alta'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $vias = $this->lista(self::VIAS);
        $categorias = $this->lista(self::CATEGORIAS);

        Schema::create('conformidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Lo que se declara conforme es un sistema de información concreto:
            // `sistemas` es «la unidad de alcance y de certificación» (§ 2.2).
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();

            $table->string('via')->default('declaracion');
            $table->string('categoria');
            $table->string('estado')->default('en_preparacion');

            /*
             * La autoevaluación de la que sale, o la auditoría externa en la vía
             * de certificación. **`restrictOnDelete`**: borrar la auditoría dejaría
             * una declaración firmada sin nada que la respalde.
             */
            $table->foreignId('auditoria_id')->constrained('auditorias')->restrictOnDelete();

            /*
             * La versión **emitida** de la Declaración de Conformidad, no la serie.
             * Lo que se declaró es un PDF concreto con su huella, y la serie sigue
             * creciendo: la v2 de dentro de dos años no puede pasar a ser la que
             * respalda la declaración de hoy.
             */
            $table->foreignId('documento_version_id')->nullable()->constrained('documento_versiones')->restrictOnDelete();

            $table->date('fecha_declaracion')->nullable();

            // Congelada al declarar, como `compromiso_cumplimientos.cubre_hasta`:
            // si la cadencia cambiara, lo ya declarado no se mueve.
            $table->date('vigente_hasta')->nullable();

            $table->string('distintivo_url', 2048)->nullable();
            $table->date('distintivo_publicado_en')->nullable();
            $table->foreignId('distintivo_evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            // Vía de certificación: modelada, no implementada.
            $table->string('entidad_certificadora')->nullable();
            $table->string('numero_certificado')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'sistema_id']);
        });

        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_via_check CHECK (via IN ({$vias}))");
        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_categoria_check CHECK (categoria IN ({$categorias}))");

        /*
         * La regla del ENS, en la base: **básica se declara y media y alta se
         * certifican**. En las dos direcciones, porque las dos son falsas —una
         * declaración de un sistema de categoría media no vale nada, y una
         * certificación de uno básico no es lo que el RD 311/2022 le pide—.
         */
        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_via_categoria_check CHECK ((via = 'declaracion') = (categoria = 'basica'))");

        // Mismo patrón que `auditorias_entidad_check`: los datos de la entidad
        // sólo tienen sentido en la vía que la tiene.
        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_certificacion_check CHECK (via = 'certificacion' OR (entidad_certificadora IS NULL AND numero_certificado IS NULL))");

        /*
         * Declarada es «hay un PDF firmado detrás, con fecha y vigencia». En
         * preparación, todavía no hay nada de eso. Retirada puede venir de
         * cualquiera de los dos, así que no se le exige ninguna forma.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE conformidades ADD CONSTRAINT conformidades_declaracion_coherente_check CHECK (
                estado = 'retirada'
                OR (estado = 'en_preparacion' AND documento_version_id IS NULL AND fecha_declaracion IS NULL AND vigente_hasta IS NULL)
                OR (estado IN ('declarada', 'publicada') AND documento_version_id IS NOT NULL AND fecha_declaracion IS NOT NULL AND vigente_hasta IS NOT NULL)
            )
        SQL);

        DB::statement('ALTER TABLE conformidades ADD CONSTRAINT conformidades_vigencia_check CHECK (vigente_hasta IS NULL OR vigente_hasta > fecha_declaracion)');

        // Publicada es «se sabe dónde y desde cuándo». Antes de publicar, no
        // hay distintivo que registrar.
        DB::statement(<<<'SQL'
            ALTER TABLE conformidades ADD CONSTRAINT conformidades_distintivo_coherente_check CHECK (
                estado = 'retirada'
                OR (estado = 'publicada' AND distintivo_url IS NOT NULL AND distintivo_publicado_en IS NOT NULL)
                OR (estado IN ('en_preparacion', 'declarada') AND distintivo_url IS NULL AND distintivo_publicado_en IS NULL AND distintivo_evidencia_id IS NULL)
            )
        SQL);

        DB::statement("ALTER TABLE conformidades ADD CONSTRAINT conformidades_distintivo_url_check CHECK (distintivo_url IS NULL OR distintivo_url ~* '^https?://')");

        /*
         * **Una en preparación por sistema, y una vigente por sistema.** Dos
         * índices y no uno, porque la renovación se prepara **mientras la
         * anterior sigue en vigor**: se renueva antes de que caduque, no después.
         * Al declarar la nueva, la anterior se retira en la misma transacción
         * (`RegistrarDeclaracion`); sin este índice, un fallo a medias dejaría dos
         * declaraciones vigentes del mismo sistema y el panel contaría dos veces.
         */
        DB::statement("CREATE UNIQUE INDEX conformidades_una_en_preparacion ON conformidades (sistema_id) WHERE estado = 'en_preparacion'");
        DB::statement("CREATE UNIQUE INDEX conformidades_una_vigente ON conformidades (sistema_id) WHERE estado IN ('declarada', 'publicada')");

        /*
         * El histórico (invariante 7), y aquí carga con el motivo de una retirada,
         * que no tiene columna propia — igual que en mejoras y en tareas.
         */
        Schema::create('conformidad_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('conformidad_id')->constrained('conformidades')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conformidad_id', 'created_at']);
        });

        DB::statement("ALTER TABLE conformidad_transiciones ADD CONSTRAINT conformidad_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE conformidad_transiciones ADD CONSTRAINT conformidad_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE conformidad_transiciones ADD CONSTRAINT conformidad_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('conformidad_transiciones');
        Schema::dropIfExists('conformidades');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
