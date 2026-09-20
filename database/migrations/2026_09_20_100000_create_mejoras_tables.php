<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oportunidades de mejora: la cláusula 10.1 de ISO 27001, «mejora continua».
 *
 * Es la segunda de las dos cláusulas que bloqueaban a la 9.3 —la otra fue la 6.2—
 * y la última entrada que le faltaba a la revisión por la dirección. Hasta aquí,
 * una oportunidad de mejora **sólo existía dentro de una auditoría**, como
 * `TipoHallazgo::OportunidadMejora`: la que se le ocurría a alguien en marzo, o la
 * que salía de un indicador que no llegaba a su objetivo, no tenía dónde
 * apuntarse.
 *
 * **Tabla propia y NO una ampliación de `no_conformidades`, y el motivo es
 * aritmético.** «No conformidades abiertas» es a la vez cifra del panel, cálculo
 * de indicador del § 4.14 y entrada obligatoria de la 9.3; con las mejoras
 * dentro, una mejora contaría como un incumplimiento en los tres sitios. Contar
 * de más es el fallo caro, y aquí se evita no dando la ocasión — es el mismo
 * argumento que dejó las subtareas fuera de `tareas` y que hizo N:M a
 * riesgo↔activo.
 *
 * Y la diferencia de fondo, que es la que la norma hace: **la 10.2 trata lo que
 * incumple y la 10.1 lo que se puede mejorar sin que nada incumpla**. Una tiene
 * causa raíz y verificación de eficacia porque algo falló; la otra no tiene nada
 * que verificar porque no hay nada roto.
 *
 * Por eso esta tabla es **mucho más corta** que `no_conformidades`: sin
 * `correccion_inmediata`, sin `analisis_causa_raiz`, sin `fecha_verificacion` y
 * sin `resultado_verificacion`. Copiar esas cuatro columnas «por simetría» sería
 * pedirle a quien apunta una idea que declare la causa raíz de una cosa que no ha
 * pasado.
 *
 * **El tratamiento son tareas**, igual que la acción correctiva del § 4.13 y por
 * lo mismo: una mejora tiene responsable, plazo, estado y coste, que es
 * literalmente `tareas`. N:M, porque una misma actuación puede materializar dos
 * mejoras.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`, `auditorias`, `no_conformidades` y
 * `objetivos_seguridad`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['propuesta', 'en_curso', 'implantada', 'descartada'];

    /** @var list<string> */
    private const ORIGENES = ['auditoria', 'revision_direccion', 'indicador', 'propia'];

    /** Los estados en los que la mejora ha dejado de estar abierta. */
    private const CERRADOS = "'implantada', 'descartada'";

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $origenes = $this->lista(self::ORIGENES);
        $cerrados = self::CERRADOS;

        Schema::create('mejoras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');

            $table->string('origen')->default('propia');

            /*
             * De qué hallazgo viene, cuando viene de uno.
             *
             * Mismo tratamiento que en `no_conformidades`, y por los mismos dos
             * motivos: **único**, porque un hallazgo se trata una vez y sin el
             * índice «hallazgos sin tratar» dependería de cuál de las dos filas se
             * mire —en PostgreSQL los nulos son distintos entre sí, así que deja
             * pasar todas las mejoras sueltas que hagan falta—; y **`nullOnDelete`
             * y no cascada**, porque borrar el hallazgo de una auditoría abierta no
             * puede llevarse por delante la prueba de que se trató.
             */
            $table->foreignId('hallazgo_id')->nullable()->constrained('hallazgos')->nullOnDelete();

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            /*
             * Qué se espera conseguir. **No es un objetivo de la 6.2** y por eso no
             * apunta a `objetivos_seguridad`: aquello es un compromiso firmado por
             * la dirección con plazo y recursos, y esto es «creemos que esto se
             * puede hacer mejor». Una mejora puede acabar convertida en objetivo, y
             * entonces el objetivo es otra fila.
             */
            $table->text('beneficio_esperado')->nullable();

            $table->string('estado')->default('propuesta');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('fecha_deteccion');

            // Nula es «nadie ha dicho para cuándo», que no es lo mismo que «no
            // corre prisa» — la misma distinción que en `tareas.fecha_limite`.
            $table->date('fecha_prevista')->nullable();

            $table->date('fecha_cierre')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->unique('hallazgo_id');
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_prevista']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement('ALTER TABLE mejoras ADD CONSTRAINT mejoras_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE mejoras ADD CONSTRAINT mejoras_titulo_check CHECK (length(trim(titulo)) > 0)');

        // En las dos direcciones, como en tareas, auditorías y no conformidades:
        // cerrada sin fecha no se puede fechar después sin inventársela, y
        // abierta con fecha es una contradicción.
        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_cierre_coherente_check CHECK ((estado IN ({$cerrados})) = (fecha_cierre IS NOT NULL))");

        /*
         * En una sola dirección, como `no_conformidades_hallazgo_origen_check`:
         * una mejora con hallazgo detrás es de origen auditoría por definición,
         * pero una de origen auditoría sin hallazgo es un caso legítimo —la que se
         * apunta a mano de una auditoría que no está registrada en Statera—.
         */
        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_hallazgo_origen_check CHECK (hallazgo_id IS NULL OR origen = 'auditoria')");

        /*
         * El tratamiento: qué se va a hacer.
         *
         * N:M, y la pivote lleva `organizacion_id` como `no_conformidad_tarea` y
         * `objetivo_tarea`. No es redundante con las dos claves: es lo que la mete
         * dentro de las tres capas de aislamiento. Y ojo —**si se olvidara,
         * `RlsDeclaradaTest` no diría nada**, porque sólo mira las tablas que ya
         * tienen la columna.
         */
        Schema::create('mejora_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('mejora_id')->constrained('mejoras')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['mejora_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        /*
         * El histórico (invariante 7), y aquí carga con el motivo de `descartada`,
         * que no tiene columna propia — igual que en tareas, en no conformidades y
         * en objetivos.
         */
        Schema::create('mejora_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('mejora_id')->constrained('mejoras')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['mejora_id', 'created_at']);
        });

        DB::statement("ALTER TABLE mejora_transiciones ADD CONSTRAINT mejora_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE mejora_transiciones ADD CONSTRAINT mejora_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE mejora_transiciones ADD CONSTRAINT mejora_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mejora_transiciones');
        Schema::dropIfExists('mejora_tarea');
        Schema::dropIfExists('mejoras');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
