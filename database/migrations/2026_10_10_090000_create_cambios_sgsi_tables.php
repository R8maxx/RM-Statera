<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los cambios del SGSI: la cláusula 6.3 de ISO 27001, «planificación de cambios».
 *
 * La norma pide una cosa y sólo una: cuando la organización decide cambiar su
 * sistema de gestión, **el cambio se hace de forma planificada**. Hasta aquí no
 * había dónde dejar constancia de esa planificación, y es lo que el auditor pide
 * ver: por qué se cambió, qué arrastraba, cómo se mantuvo el sistema en pie
 * mientras tanto y quién lo decidió.
 *
 * **Sólo cambios del propio SGSI** —alcance, política, organización y roles,
 * procesos del sistema de gestión, recursos, documentación—. Los cambios
 * técnicos de A.8.32 y `op.exp.5` son otra cosa: un parche o una migración de
 * servidor se gestionan por decenas y con otro ritmo, y meterlos aquí ahogaría
 * los tres o cuatro cambios al año que la 6.3 quiere ver planificados.
 *
 * **Es el registro de mejoras con la firma de los objetivos.** De la 10.1 toma el
 * esqueleto —código, transiciones con histórico, actuaciones como tareas—; de
 * la 6.2, la regla que lo define: un cambio **propuesto** se escribe como se
 * pueda, uno **aprobado** es un compromiso con firma y plazo, y eso lo imponen
 * dos `CHECK`.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `mejoras` y `objetivos_seguridad`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['propuesto', 'aprobado', 'implantado', 'revisado', 'descartado'];

    /** @var list<string> */
    private const AMBITOS = ['alcance', 'politica', 'organizacion', 'proceso', 'recursos', 'documentacion', 'otro'];

    /** @var list<string> */
    private const ORIGENES = ['propio', 'revision_direccion', 'auditoria', 'no_conformidad', 'contexto'];

    /** Los estados en que el cambio compromete: firma y plazo obligatorios. */
    private const COMPROMETIDOS = "'aprobado', 'implantado', 'revisado'";

    /** Los estados en que el cambio ya está hecho. */
    private const HECHOS = "'implantado', 'revisado'";

    /** Los estados en que ha dejado de estar abierto. */
    private const CERRADOS = "'revisado', 'descartado'";

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $ambitos = $this->lista(self::AMBITOS);
        $origenes = $this->lista(self::ORIGENES);
        $comprometidos = self::COMPROMETIDOS;
        $hechos = self::HECHOS;
        $cerrados = self::CERRADOS;

        Schema::create('cambios_sgsi', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->string('ambito');

            // Sólo etiqueta, sin clave foránea: el mismo reparto que
            // `mejoras.origen` cuando viene de una revisión por la dirección.
            $table->string('origen')->default('propio');

            /*
             * Las cuatro preguntas de un cambio planificado. Texto libre y
             * opcional en el borrador: obligar a rellenarlas al apuntarlo
             * impediría apuntarlo el día que se decide, que es cuando se apunta.
             */
            $table->text('proposito')->nullable();
            $table->text('consecuencias')->nullable();
            $table->text('integridad')->nullable();
            $table->text('recursos')->nullable();

            $table->string('estado')->default('propuesto');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('fecha_propuesta');
            $table->date('fecha_prevista')->nullable();
            $table->date('fecha_implantacion')->nullable();
            $table->date('fecha_cierre')->nullable();

            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();

            /*
             * Si el cambio consiguió lo que pretendía. Se escribe al cerrarlo y
             * es lo que separa «se hizo» de «se hizo y sirvió».
             */
            $table->text('revision')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_prevista']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_ambito_check CHECK (ambito IN ({$ambitos}))");
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement('ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_titulo_check CHECK (length(trim(titulo)) > 0)');

        // La regla del módulo: comprometerse es con plazo y con firma.
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_plazo_check CHECK (estado NOT IN ({$comprometidos}) OR fecha_prevista IS NOT NULL)");
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_firma_exigida_check CHECK (estado NOT IN ({$comprometidos}) OR aprobado_en IS NOT NULL)");
        DB::statement('ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_firma_coherente_check CHECK ((aprobado_en IS NULL) = (aprobado_por_id IS NULL))');

        // En las dos direcciones, como el cierre: hecho sin fecha no se puede
        // fechar después sin inventársela, y sin hacer con fecha es una
        // contradicción.
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_implantacion_coherente_check CHECK ((estado IN ({$hechos})) = (fecha_implantacion IS NOT NULL))");
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_cierre_coherente_check CHECK ((estado IN ({$cerrados})) = (fecha_cierre IS NOT NULL))");

        // Revisado sin decir si sirvió es un «hecho» con otro nombre.
        DB::statement("ALTER TABLE cambios_sgsi ADD CONSTRAINT cambios_sgsi_revision_check CHECK (estado <> 'revisado' OR length(trim(coalesce(revision, ''))) > 0)");

        /*
         * Lo que se va a hacer, como tareas. La pivote lleva `organizacion_id`
         * como todas las de su familia: es lo que la mete en las tres capas, y si
         * faltara `RlsDeclaradaTest` no lo diría.
         */
        Schema::create('cambio_sgsi_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('cambio_sgsi_id')->constrained('cambios_sgsi')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cambio_sgsi_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        // El histórico (invariante 7). Carga con el motivo de descartar y de
        // reabrir, que no tienen columna propia.
        Schema::create('cambio_sgsi_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('cambio_sgsi_id')->constrained('cambios_sgsi')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['cambio_sgsi_id', 'created_at']);
        });

        DB::statement("ALTER TABLE cambio_sgsi_transiciones ADD CONSTRAINT cambio_sgsi_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE cambio_sgsi_transiciones ADD CONSTRAINT cambio_sgsi_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE cambio_sgsi_transiciones ADD CONSTRAINT cambio_sgsi_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cambio_sgsi_transiciones');
        Schema::dropIfExists('cambio_sgsi_tarea');
        Schema::dropIfExists('cambios_sgsi');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
