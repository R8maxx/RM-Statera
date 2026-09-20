<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Objetivos de seguridad de la información: la cláusula 6.2 de ISO 27001.
 *
 * Es una de las cinco cláusulas que tenían requisito en el catálogo, implantación
 * esperando y **ningún sitio donde escribirse** — exactamente lo que le pasaba al
 * § 4.1 hasta que se construyó. Y es la primera de las dos que bloquean a la 9.3:
 * de las siete entradas obligatorias de la revisión por la dirección, «el
 * cumplimiento de los objetivos de seguridad» no salía de ninguna parte.
 *
 * **Va después del § 4.14 y no antes, y eso da forma a la tabla.** La 6.2 pide
 * seis cosas del objetivo y cinco de su planificación, y dos de ellas ya existen
 * en el producto:
 *
 * 1. **«Cómo se evaluarán los resultados» (6.2, planificación e) ES un
 *    indicador**, así que no es una columna de texto: es `indicador_objetivo`,
 *    **N:M** como ya lo dejó anunciado CLAUDE.md al cerrar el § 4.14 —«un
 *    indicador evalúa varios objetivos y un objetivo necesita varios»—. Al revés,
 *    el objetivo nacería con el campo que el auditor más mira y nada detrás.
 *
 * 2. **«Qué se hará» (planificación a) son tareas**, y el vínculo es N:M por el
 *    mismo argumento que las acciones correctivas del § 4.13: una actuación hace
 *    avanzar varios objetivos a la vez, y con una columna de texto el trabajo
 *    quedaría fuera del tablero, del calendario y del aviso diario.
 *
 * Lo que sí es columna es **«qué recursos» (planificación b)**, que es texto libre
 * y no sale de ninguna tabla: el presupuesto de un objetivo no es el coste de sus
 * tareas —hay objetivos que se cumplen con horas de gente que ya está—.
 *
 * **Dos `CHECK` cuelgan del compromiso, y son la regla del módulo.** Un objetivo
 * en borrador se escribe como se pueda; uno **aprobado** es la dirección
 * comprometiéndose, y ahí la norma exige por escrito para cuándo (planificación d)
 * y quién responde. Así que el plazo y la firma no son obligatorios para
 * `propuesto` ni para `retirado`, y sí para los tres estados comprometidos. Es el
 * mismo reparto que hace `documento_versiones` con su firma y el que impide
 * aprobar un análisis del contexto sin contestar a la pregunta del clima.
 *
 * `fecha_cierre` acopla en las dos direcciones, como en `tareas`, en `auditorias`
 * y en `no_conformidades`: la pregunta del auditor no es si el objetivo se cerró,
 * es desde cuándo.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`, `auditorias` y `no_conformidades`. El de
 * `documentos` hace lo contrario y está anotado como trampa: enumerando desde el
 * enum, `migrate:fresh` incluye los valores nuevos aunque falte la migración que
 * los añade, así que ningún test se pone rojo si se olvida.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['propuesto', 'aprobado', 'alcanzado', 'no_alcanzado', 'retirado'];

    /**
     * Los estados en los que la dirección ya se ha comprometido.
     *
     * Son los que exigen plazo y firma. `retirado` no está: se puede retirar un
     * objetivo que nunca llegó a aprobarse, y obligarle a tener firma sería
     * fabricar una que nadie puso.
     */
    private const COMPROMETIDOS = "'aprobado', 'alcanzado', 'no_alcanzado'";

    /** Los estados en los que el objetivo ha dejado de estar vivo. */
    private const CERRADOS = "'alcanzado', 'no_alcanzado', 'retirado'";

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $comprometidos = self::COMPROMETIDOS;
        $cerrados = self::CERRADOS;

        Schema::create('objetivos_seguridad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Único dentro de la organización, como el de una no conformidad: dos
            // clientes pueden llamar igual a su primer objetivo de 2026.
            $table->string('codigo');

            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->string('estado')->default('propuesto');

            /*
             * Quién responde (6.2, planificación c). Nulo es «nadie lo ha cogido»,
             * que no es lo mismo que «no hace falta nadie» — la misma distinción
             * que en `tareas.responsable_id`.
             */
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Qué recursos hacen falta (6.2, planificación b), en texto libre.
             *
             * **No se deduce del coste de sus tareas**: hay objetivos que se
             * cumplen con horas de gente que ya está y sin gastar un euro, y una
             * cifra a cero se leería como «no hace falta nada» en vez de como «no
             * cuesta dinero». Lo que el auditor lee aquí es de dónde va a salir.
             */
            $table->text('recursos')->nullable();

            // Para cuándo (6.2, planificación d). Exigida al aprobar; ver la
            // cabecera y el `CHECK` de más abajo.
            $table->date('fecha_objetivo')->nullable();

            $table->date('fecha_cierre')->nullable();

            /*
             * La firma de la dirección.
             *
             * Columna y no sólo histórico, igual que `verificada_por_id` en una no
             * conformidad y `aprobada_por_id` en una versión: es lo que el auditor
             * busca al lado de la fecha, y la ficha la enseña sin recorrer las
             * transiciones.
             */
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->text('nota_aprobacion')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_objetivo']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_estado_check CHECK (estado IN ({$estados}))");
        DB::statement('ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_titulo_check CHECK (length(trim(titulo)) > 0)');

        // Las dos mitades del compromiso. Ver la cabecera.
        DB::statement("ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_plazo_check CHECK (estado NOT IN ({$comprometidos}) OR fecha_objetivo IS NOT NULL)");
        DB::statement("ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_firma_exigida_check CHECK (estado NOT IN ({$comprometidos}) OR aprobado_en IS NOT NULL)");

        // Una firma es quién y cuándo, o no es una firma. Sin esto, media firma
        // pasaría y la ficha enseñaría una fecha sin nombre.
        DB::statement('ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_firma_coherente_check CHECK ((aprobado_en IS NULL) = (aprobado_por_id IS NULL))');

        // En las dos direcciones, como en tareas: cerrado sin fecha no se puede
        // fechar después sin inventársela, y vivo con fecha es una contradicción.
        DB::statement("ALTER TABLE objetivos_seguridad ADD CONSTRAINT objetivos_seguridad_cierre_coherente_check CHECK ((estado IN ({$cerrados})) = (fecha_cierre IS NOT NULL))");

        /*
         * Cómo se evalúan los resultados (6.2, planificación e).
         *
         * **N:M, y la pivote lleva `organizacion_id`** como `implantacion_tarea` y
         * `no_conformidad_tarea`. No es redundante con las dos claves: es lo que la
         * mete dentro de las tres capas de aislamiento. Y ojo —**si se olvidara,
         * `RlsDeclaradaTest` no diría nada**, porque sólo mira las tablas que ya
         * tienen la columna.
         *
         * N:M en los dos sentidos por un motivo real: «porcentaje de implantación
         * del ENS» evalúa a la vez el objetivo de adecuación y el de madurez, y un
         * objetivo como «reducir la exposición» necesita más de una cifra para no
         * ser una consigna.
         */
        Schema::create('indicador_objetivo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('objetivo_id')->constrained('objetivos_seguridad')->cascadeOnDelete();
            $table->foreignId('indicador_id')->constrained('indicadores')->cascadeOnDelete();

            $table->foreignId('vinculado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['objetivo_id', 'indicador_id']);
            $table->index(['organizacion_id', 'indicador_id']);
        });

        /*
         * Qué se va a hacer (6.2, planificación a): las actuaciones, que son tareas.
         *
         * Mismo patrón que `no_conformidad_tarea`, y **sin doble vínculo** a
         * `implantacion_tarea`, a diferencia de la acción correctiva: allí hacía
         * falta porque el plan de adecuación imprimía «sin trabajo planificado»
         * sobre una medida que sí lo tenía, y aquí no hay medida detrás por
         * construcción. La consecuencia, declarada: el coste de esa tarea no entra
         * en el presupuesto del plan, porque ese plan presupuesta medidas. Es el
         * mismo reparto que se escribió para `cuestion_tarea` en el § 4.1.
         */
        Schema::create('objetivo_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('objetivo_id')->constrained('objetivos_seguridad')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['objetivo_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        /*
         * El histórico, como en tareas, implantaciones y no conformidades: el
         * auditor no pregunta si el objetivo está aprobado, pregunta desde cuándo
         * (invariante 7). Y aquí carga además con el motivo de `retirado` y con el
         * porqué de `no_alcanzado`, que no tienen columna.
         */
        Schema::create('objetivo_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('objetivo_id')->constrained('objetivos_seguridad')->cascadeOnDelete();

            // Nulo en el alta: no venía de ningún estado.
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['objetivo_id', 'created_at']);
        });

        DB::statement("ALTER TABLE objetivo_transiciones ADD CONSTRAINT objetivo_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE objetivo_transiciones ADD CONSTRAINT objetivo_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
        DB::statement('ALTER TABLE objetivo_transiciones ADD CONSTRAINT objetivo_transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('objetivo_transiciones');
        Schema::dropIfExists('objetivo_tarea');
        Schema::dropIfExists('indicador_objetivo');
        Schema::dropIfExists('objetivos_seguridad');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
