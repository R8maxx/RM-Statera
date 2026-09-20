<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La revisión por la dirección: § 4.15 y la cláusula 9.3 de ISO 27001.
 *
 * Es el módulo que llevaba bloqueado desde el principio, y no por su complejidad:
 * la 9.3 tiene **siete entradas obligatorias** y hasta ahora dos no salían de
 * ninguna parte —el cumplimiento de los objetivos de seguridad (6.2) y las
 * oportunidades de mejora (10.1)—. Con esos dos módulos dentro, las siete existen
 * y esto puede recogerlas.
 *
 * **Ojo con el nombre.** `/revisiones` ya está ocupada por las revisiones del
 * inventario de activos (`RevisionInventario`), que son otra cosa: aquéllas son el
 * «inventario mantenido» que piden A.5.9 y `op.exp.1`. Esta ruta es
 * `/revision-direccion` y el modelo `RevisionDireccion`. Mismo caso que `Contexto`
 * frente a `ContextoOrganizacion` y que `Domain\Traza` frente a
 * `Domain\Auditoria`: se anota, no se renombra lo que ya está.
 *
 * ### La instantánea es lo que da forma al módulo
 *
 * Las siete entradas se **congelan al aprobar** y nunca se consultan en vivo. Es
 * el fallo más caro que este módulo podía tener, y ya está documentado cuatro
 * veces en el repositorio: el acta de marzo enseñaría las cifras de octubre.
 * Precedentes: `documento_versiones.instantanea`, `analisis_contexto.instantanea`,
 * la exigencia congelada al cerrar una auditoría y `mediciones.objetivo`.
 *
 * Y a diferencia del análisis del contexto, aquí **no hay vigente**: cada revisión
 * es un acto con su fecha, y las anteriores no se jubilan. Por eso no hay índice
 * único parcial ni estado `obsoleta`.
 *
 * ### Las salidas son tareas
 *
 * La 9.3.3 pide registrar las decisiones, y una decisión que no acaba en algo que
 * alguien hace para una fecha es un acta que no sirve. `revision_tarea` es N:M
 * como sus hermanas, y **lee en los dos sentidos**: las salidas de esta revisión
 * y, desde la siguiente, «el estado de las acciones de revisiones previas» — que
 * es literalmente la primera entrada obligatoria de la 9.3.2.
 *
 * ### Tres estados, calcados de `auditorias`
 *
 * `planificada` (está convocada), `en_curso` (se está celebrando y se recogen las
 * entradas) y `aprobada` (el acta está firmada). La comparación con auditorías no
 * es casual: las dos son un acto que ocurre en una fecha, que se prepara antes y
 * que se congela al terminar.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`, `auditorias`, `no_conformidades`,
 * `objetivos_seguridad` y `mejoras`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['planificada', 'en_curso', 'aprobada'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);

        Schema::create('revisiones_direccion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Único dentro de la organización, como el de una auditoría: es como
            // se cita en un acta —«la RD-2026-01»—.
            $table->string('codigo');

            // Cuándo se celebra. No se deduce del periodo: una revisión del
            // ejercicio 2025 puede celebrarse en febrero de 2026, y de hecho es lo
            // normal.
            $table->date('fecha');

            /*
             * El periodo que se revisa.
             *
             * **Dos columnas y no una periodicidad**, a diferencia de un indicador:
             * una revisión por la dirección no parte el calendario en cubos
             * iguales. La primera cubre desde que se implantó el SGSI, y una
             * extraordinaria puede cubrir seis semanas. Lo que hace falta es saber
             * **de qué habla el acta**, y eso son dos fechas.
             */
            $table->date('periodo_desde');
            $table->date('periodo_hasta');

            // Quiénes estuvieron. Texto libre y no una tabla de asistentes: § 4.8
            // no existe, `users` son cuentas de Statera y a una revisión por la
            // dirección asiste gente que no tiene cuenta. Va declarado.
            $table->text('asistentes')->nullable();

            $table->string('estado')->default('planificada');

            /*
             * Las siete entradas de la 9.3.2, congeladas al aprobar. Ver la
             * cabecera: sin esto el acta de marzo enseñaría las cifras de octubre.
             */
            $table->jsonb('instantanea')->nullable();

            // Lo que la dirección concluyó. Es la parte del acta que escribe una
            // persona; todo lo demás se calcula.
            $table->text('conclusiones')->nullable();

            $table->foreignId('aprobada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobada_en')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha']);
        });

        DB::statement("ALTER TABLE revisiones_direccion ADD CONSTRAINT revisiones_direccion_estado_check CHECK (estado IN ({$estados}))");
        DB::statement('ALTER TABLE revisiones_direccion ADD CONSTRAINT revisiones_direccion_codigo_check CHECK (length(trim(codigo)) > 0)');

        // Un periodo al revés no es un periodo, y es el dedazo más fácil de dar
        // en un formulario con dos fechas.
        DB::statement('ALTER TABLE revisiones_direccion ADD CONSTRAINT revisiones_direccion_periodo_check CHECK (periodo_hasta >= periodo_desde)');

        /*
         * Aprobada exige las dos cosas: **firma e instantánea**.
         *
         * La segunda es la que importa y es lo que separa este `CHECK` del de un
         * objetivo: un acta aprobada sin las entradas congeladas es un acta que no
         * puede demostrar de qué habló, y eso es exactamente lo que la cláusula 9.3
         * pide poder enseñar.
         *
         * En una sola dirección, como el de `documento_versiones`: la vuelta a
         * `en_curso` conserva la firma anterior hasta que la siguiente aprobación
         * la sobreescribe, y exigir lo contrario obligaría a limpiarla en una
         * escritura que el trigger de inmutabilidad ya está vigilando.
         */
        DB::statement("ALTER TABLE revisiones_direccion ADD CONSTRAINT revisiones_direccion_firma_check CHECK (estado <> 'aprobada' OR (aprobada_en IS NOT NULL AND instantanea IS NOT NULL))");

        // Una firma es quién y cuándo, o no es una firma.
        DB::statement('ALTER TABLE revisiones_direccion ADD CONSTRAINT revisiones_direccion_firma_coherente_check CHECK ((aprobada_en IS NULL) = (aprobada_por_id IS NULL))');

        /*
         * Las salidas de la revisión (9.3.3).
         *
         * N:M, y la pivote lleva `organizacion_id` como todas sus hermanas. No es
         * redundante con las dos claves: es lo que la mete dentro de las tres capas
         * de aislamiento. Y ojo —**si se olvidara, `RlsDeclaradaTest` no diría
         * nada**, porque sólo mira las tablas que ya tienen la columna.
         *
         * **Se lee en los dos sentidos**: hacia delante son las decisiones de esta
         * revisión, y hacia atrás son «el estado de las acciones de revisiones
         * previas», que es la primera entrada obligatoria de la 9.3.2.
         */
        Schema::create('revision_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('revision_direccion_id')->constrained('revisiones_direccion')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['revision_direccion_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_tarea');
        Schema::dropIfExists('revisiones_direccion');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
