<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No conformidades y acciones correctivas: § 4.13, y la cláusula 10.2 de ISO.
 *
 * Es la otra mitad del módulo de auditorías. Un hallazgo dice qué se encontró; sin
 * esto, nadie contesta qué se hizo con él, y una auditoría cuyos hallazgos no van
 * a ninguna parte no cierra ningún ciclo. La cláusula 10.2 pide cuatro cosas y en
 * este orden: reaccionar y corregir, analizar la causa, implantar la acción
 * correctiva y **comprobar que funcionó** — lo último es lo que más se olvida y lo
 * que el auditor comprueba.
 *
 * Cuatro desvíos respecto a lo que dibuja § 2.2, y el primero da forma al resto:
 *
 * 1. **`accion_correctiva` no es una columna de texto: es una tarea.** Una acción
 *    correctiva tiene responsable, plazo, estado y coste, que es literalmente
 *    `tareas`. Con una columna de texto, el trabajo correctivo quedaría fuera del
 *    tablero, del calendario, del aviso diario y del presupuesto del plan de
 *    adecuación — cinco sitios donde hay que verlo. Y el vínculo es **N:M**, como
 *    `implantacion_tarea` y por lo mismo: «implantar MFA» cierra a la vez una no
 *    conformidad de la auditoría ISO y otra de la autoevaluación del ENS, y
 *    apuntarla dos veces sería volver a las hojas de cálculo duplicadas.
 *
 * 2. **Dos columnas de fecha y dos `CHECK`, no una.** `fecha_cierre` es cuándo se
 *    dio por tratada y `fecha_verificacion` cuándo se comprobó que la corrección
 *    había servido. Son dos momentos distintos —la eficacia se mira semanas
 *    después, cuando hay con qué mirarla—, y con una sola columna la verificación
 *    que llega en noviembre no tiene dónde fecharse. `anulada` entra en el
 *    acoplamiento del cierre por el mismo argumento que metió `descartada` en el
 *    de tareas: la pregunta del auditor es «¿desde cuándo dejó de estar abierta?».
 *
 * 3. **`eficacia_verificada` no es un booleano, es un estado.** La § 2.2 lo dibuja
 *    como bandera, y sería el mismo dato que `estado = 'verificada'` en dos sitios
 *    que pueden desincronizarse. Lo que sí merece columna es
 *    `resultado_verificacion`: **qué** se comprobó, que es lo que el auditor lee al
 *    lado de la fecha. Mismo reparto que `nota_aceptacion` en riesgos.
 *
 * 4. **La verificación fallida no es un estado, es una transición de vuelta** a
 *    `en_tratamiento`. Un estado `no_eficaz` se quedaría puesto sobre una no
 *    conformidad que sigue abierta, y volvería a contarse como cerrada en cuanto
 *    alguien lo mirara por encima. El precedente exacto es
 *    `EstadoAuditoria::Cerrada → EnCurso`. Esa vuelta suelta las dos fechas, igual
 *    que reabrir una auditoría suelta la suya.
 *
 * Y una decisión que no es desvío sino herencia: **el motivo de `anulada` vive en
 * la nota de la transición y no en columna propia**, exactamente como `descartada`
 * en tareas y `rechazado` en documentos. La excepción que sí ganó columna fue
 * `riesgos.nota_aceptacion`, y su argumento no vale aquí: allí son dos personas en
 * dos momentos y una sola columna haría que firmar pisara el razonamiento.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas` y de `auditorias`. El de `documentos` hace lo
 * contrario y está anotado como trampa: enumerando desde el enum, `migrate:fresh`
 * incluye los valores nuevos aunque falte la migración que los añade, así que
 * ningún test se pone rojo si se olvida.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['abierta', 'en_tratamiento', 'cerrada', 'verificada', 'anulada'];

    /** @var list<string> */
    private const ORIGENES = ['auditoria', 'incidente', 'revision_direccion', 'propia'];

    /** Los estados en los que la no conformidad ha dejado de estar abierta. */
    private const CERRADOS = "'cerrada', 'verificada', 'anulada'";

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $origenes = $this->lista(self::ORIGENES);
        $cerrados = self::CERRADOS;

        Schema::create('no_conformidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Único dentro de la organización, como el de una auditoría: dos
            // clientes pueden llamar igual a su primera no conformidad de 2026.
            $table->string('codigo');

            $table->string('origen')->default('propia');

            /*
             * De qué hallazgo viene, cuando viene de uno.
             *
             * **`nullOnDelete` y no cascada**, a diferencia de casi todo lo demás
             * del módulo: la no conformidad es el registro del tratamiento y puede
             * tener acciones correctivas cerradas colgando. Borrar el hallazgo de
             * una auditoría abierta —corregir una línea mal escrita— no puede
             * llevarse por delante lo que se hizo para arreglarlo; eso sería
             * borrar la prueba de que se trató.
             *
             * Único: un hallazgo se trata una vez. Sin esto, «hallazgos sin
             * tratar» dependería de cuál de las dos filas se mire, y el registro
             * enseñaría el mismo hecho dos veces. En PostgreSQL los nulos son
             * distintos entre sí, así que un índice único normal deja pasar todas
             * las no conformidades sueltas que hagan falta.
             */
            $table->foreignId('hallazgo_id')->nullable()->constrained('hallazgos')->nullOnDelete();

            $table->text('descripcion');

            /*
             * Lo que se hizo el mismo día para contener el problema, que la
             * cláusula 10.2 a) pide antes que nada y que **no** es la acción
             * correctiva: «se revocó la cuenta» no ataca la causa, tapa el agujero
             * mientras se ataca. No genera tarea porque ya está hecho cuando se
             * escribe.
             */
            $table->text('correccion_inmediata')->nullable();

            // 10.2 b): por qué pasó. Sin esto la acción correctiva trata el
            // síntoma y la no conformidad vuelve el año que viene.
            $table->text('analisis_causa_raiz')->nullable();

            $table->string('estado')->default('abierta');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Cuándo se detectó, que es donde empieza a correr el reloj. En una de
             * auditoría es la fecha de la auditoría, y aun así no se deduce de
             * ella: una detectada en la revisión por la dirección o en un
             * incidente no tiene auditoría de la que deducirla.
             */
            $table->date('fecha_deteccion');

            // Para cuándo se espera tenerla tratada. Nula es «nadie ha dicho para
            // cuándo», que no es lo mismo que «no corre prisa» — la misma
            // distinción que en `tareas.fecha_limite`.
            $table->date('fecha_prevista')->nullable();

            $table->date('fecha_cierre')->nullable();

            $table->date('fecha_verificacion')->nullable();
            $table->foreignId('verificada_por_id')->nullable()->constrained('users')->nullOnDelete();

            // Qué se comprobó, no si se comprobó: ver el punto 3 de la cabecera.
            $table->text('resultado_verificacion')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->unique('hallazgo_id');
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_prevista']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_descripcion_check CHECK (length(trim(descripcion)) > 0)');

        // Las dos mitades del punto 2 de la cabecera, las dos en ambas
        // direcciones como en `tareas` y en `auditorias`: cerrada sin fecha no se
        // puede fechar después sin inventársela, y abierta con fecha es una
        // contradicción.
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_cierre_coherente_check CHECK ((estado IN ({$cerrados})) = (fecha_cierre IS NOT NULL))");
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_verificacion_coherente_check CHECK ((estado = 'verificada') = (fecha_verificacion IS NOT NULL))");

        // Comprobar la eficacia antes de haber cerrado el tratamiento es
        // comprobar que funciona algo que todavía no se ha terminado.
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_orden_fechas_check CHECK (fecha_verificacion IS NULL OR fecha_cierre IS NULL OR fecha_verificacion >= fecha_cierre)');

        /*
         * En una sola dirección, como `auditorias_entidad_check`: una no
         * conformidad con hallazgo detrás es de origen auditoría por definición,
         * pero una de origen auditoría sin hallazgo es un caso legítimo —la que se
         * apunta a mano de una auditoría que no está registrada en Statera—. Con
         * la implicación en las dos direcciones, elegir «auditoría» en el
         * desplegable fallaría con un error de restricción que no dice por qué.
         */
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_hallazgo_origen_check CHECK (hallazgo_id IS NULL OR origen = 'auditoria')");

        /*
         * Las acciones correctivas.
         *
         * N:M, y la pivote lleva `organizacion_id` como `implantacion_tarea`. No es
         * redundante con las dos claves: es lo que la mete dentro de las tres capas
         * de aislamiento. Y ojo —**si se olvidara, `RlsDeclaradaTest` no diría
         * nada**, porque sólo mira las tablas que ya tienen la columna.
         */
        Schema::create('no_conformidad_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('no_conformidad_id')->constrained('no_conformidades')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['no_conformidad_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        /*
         * El histórico, como en tareas y en implantaciones: el auditor no pregunta
         * si la no conformidad está cerrada, pregunta desde cuándo (invariante 7).
         * Y aquí carga además con el motivo de `anulada`, que no tiene columna.
         */
        Schema::create('no_conformidad_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('no_conformidad_id')->constrained('no_conformidades')->cascadeOnDelete();

            // Nulo en el alta: no venía de ningún estado.
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['no_conformidad_id', 'created_at']);
        });

        DB::statement("ALTER TABLE no_conformidad_transiciones ADD CONSTRAINT no_conformidad_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE no_conformidad_transiciones ADD CONSTRAINT no_conformidad_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE no_conformidad_transiciones ADD CONSTRAINT no_conformidad_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('no_conformidad_transiciones');
        Schema::dropIfExists('no_conformidad_tarea');
        Schema::dropIfExists('no_conformidades');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
