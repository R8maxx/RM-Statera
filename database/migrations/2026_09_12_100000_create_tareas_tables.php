<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El plan de acción: lo que hay que hacer, quién y para cuándo.
 *
 * Es la última pieza de la fase 1 de la especificación —la que sustituye las
 * hojas de cálculo—, y la que convierte «esto está pendiente» en «esto lo hace
 * fulano antes del día tal». Sin ella el panel sabe lo que falta y nadie sabe
 * quién lo coge.
 *
 * **`origen` se declara entero y hoy sólo se cablea uno.** Los cinco valores son
 * los de § 4.7 —hallazgo, riesgo, brecha de implantación, incidente y revisión
 * por la dirección— y de esos sólo existe el tercero: los otros cuatro llegan con
 * sus módulos. Es el mismo criterio con el que se carga el Anexo II completo
 * usando el subconjunto de categoría básica: el modelo entero desde el principio
 * y los datos que haya.
 *
 * El vínculo con implantaciones es N:M y no una clave en la tarea: «revisar la
 * política de contraseñas» cierra a la vez un control de ISO y tres medidas del
 * ENS, que es exactamente el argumento del producto.
 *
 * El histórico va aparte, como en implantaciones: el auditor no pregunta si la
 * tarea está cerrada, pregunta desde cuándo (invariante 7).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['pendiente', 'en_curso', 'bloqueada', 'hecha', 'descartada'];

    /** @var list<string> */
    private const ORIGENES = ['hallazgo', 'riesgo', 'brecha_implantacion', 'incidente', 'revision_direccion', 'propia'];

    /** @var list<string> */
    private const PRIORIDADES = ['baja', 'media', 'alta', 'critica'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $origenes = $this->lista(self::ORIGENES);
        $prioridades = $this->lista(self::PRIORIDADES);

        Schema::create('tareas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->string('origen')->default('propia');
            $table->string('estado')->default('pendiente');
            $table->string('prioridad')->default('media');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_limite')->nullable();

            /*
             * Cuándo se cerró, que no es lo mismo que cuándo se tocó por última
             * vez. `updated_at` cambia al corregir una falta de ortografía en el
             * título; esto es el hecho que se le enseña al auditor.
             */
            $table->date('fecha_cierre')->nullable();

            /*
             * En euros y con dos decimales. § 4.7 lo pide para el plan de
             * adecuación, donde una tarea sin coste no se puede presupuestar.
             */
            $table->decimal('coste_estimado', 12, 2)->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'estado']);
            // El índice del aviso diario y de la vista de vencidas.
            $table->index(['organizacion_id', 'fecha_limite']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_prioridad_check CHECK (prioridad IN ({$prioridades}))");

        // Un título en blanco deja una fila que nadie sabe qué es.
        DB::statement('ALTER TABLE tareas ADD CONSTRAINT tareas_titulo_check CHECK (length(trim(titulo)) > 0)');

        // Una tarea cerrada sin fecha de cierre no se puede fechar después sin
        // inventársela, y una abierta con fecha de cierre es una contradicción.
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_cierre_coherente_check CHECK ((estado IN ('hecha', 'descartada')) = (fecha_cierre IS NOT NULL))");

        DB::statement('ALTER TABLE tareas ADD CONSTRAINT tareas_coste_check CHECK (coste_estimado IS NULL OR coste_estimado >= 0)');

        Schema::create('implantacion_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tarea_id', 'implantacion_id']);
            $table->index(['organizacion_id', 'implantacion_id']);
        });

        Schema::create('tarea_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            // Nulo en el alta: no venía de ningún estado.
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tarea_id', 'created_at']);
        });

        DB::statement("ALTER TABLE tarea_transiciones ADD CONSTRAINT tarea_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE tarea_transiciones ADD CONSTRAINT tarea_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE tarea_transiciones ADD CONSTRAINT tarea_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_transiciones');
        Schema::dropIfExists('implantacion_tarea');
        Schema::dropIfExists('tareas');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
    }
};
