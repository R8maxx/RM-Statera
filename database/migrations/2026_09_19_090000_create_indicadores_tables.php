<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indicadores y mediciones: el § 4.14 y la cláusula 9.1 de ISO.
 *
 * El panel lleva desde el principio contando cosas —cumplimiento, inventario,
 * plan de acción, no conformidades— y todas esas cifras son de **hoy**. La 9.1
 * no pide una cifra, pide un seguimiento: qué se mide, con qué método, **cada
 * cuánto**, quién lo mira y **contra qué objetivo**. Sin la serie, «¿ha mejorado
 * esto desde la última revisión?» —que es literalmente lo que la 9.3 pregunta—
 * se contesta con un encogimiento de hombros.
 *
 * Va antes que los objetivos de seguridad (6.2) porque el «cómo se evaluarán los
 * resultados» que esa cláusula exige **es** un indicador: al revés, el objetivo
 * nacería con el campo que el auditor más mira y nada detrás.
 *
 * Siete decisiones que no se deducen del esquema:
 *
 * 1. **La medición se sella, no se recalcula.** «Calculado» significa que el
 *    sistema **propone** la cifra al cerrar el periodo y la guarda con su fecha,
 *    no que se vuelva a consultar al mirarla. Una serie que se recalcula
 *    reescribiría marzo en octubre — el argumento literal de
 *    `riesgo_valoraciones.salvaguardas`, `documento_versiones.instantanea`, la
 *    checklist congelada al cerrar una auditoría y la instantánea del análisis
 *    del contexto. Cuatro precedentes.
 *
 * 2. **Y se sella con su objetivo al lado** (`mediciones.objetivo`). Sin él,
 *    subir el listón en marzo reescribiría el veredicto de enero: lo que en su
 *    día estuvo en objetivo pasaría a figurar como fallado y nadie sabría por
 *    qué. El objetivo vigente vive en el indicador; el que se aplicó, en la fila.
 *    Mismo reparto que la exigencia congelada en `auditoria_puntos`.
 *
 * 3. **`mediciones.origen` NO es una copia de `indicadores.origen`.** El del
 *    indicador es la política de hoy; el de la fila es el hecho de cómo se
 *    obtuvo **aquélla**. Pasar un indicador de calculado a manual no puede
 *    reescribir cómo se tomó la medición de marzo.
 *
 * 4. **Numerador y denominador en columnas propias, no sólo el valor.** «80 %»
 *    sobre 5 y sobre 300 no dicen lo mismo, y aquí toda cifra viaja con su
 *    denominador. Van los dos o no va ninguno: un numerador suelto no se lee.
 *
 * 5. **`calculo` es un enum cerrado, no una fórmula.** § 2.2 dice
 *    «formula_o_fuente» y la tentación es una cadena que alguien evalúe. No:
 *    cada cálculo apunta a un **scope que ya existe** —`Implantacion::pendientes()`,
 *    `Evidencia::caducadas()`, `NoConformidad::pendientesDeVerificar()`—, que es
 *    lo mismo que hace `Filtro::porScope()` y lo que garantiza que el indicador y
 *    la lista que sale al pulsarlo cuenten lo mismo. De paso, no entra un
 *    evaluador de expresiones en una herramienta que está en el alcance de su
 *    propio SGSI. El indicador **manual** existe igual y declara su método en
 *    `formula_o_fuente`: la 9.1 b) pregunta por el método, y «lo cuenta Marta a
 *    mano desde la lista de asistencia firmada» es una respuesta válida y la
 *    única posible mientras no exista el § 4.8.
 *
 * 6. **`sentido` es obligatorio aunque `objetivo` sea opcional.** «Tareas
 *    vencidas ≤ 5» y «cobertura de cifrado ≥ 90 %» se juzgan al revés, y sin la
 *    columna el veredicto sale invertido en la mitad de los indicadores. Que el
 *    objetivo sea nulo es legítimo —vigilar algo sin comprometerse a una cifra
 *    sigue siendo seguimiento—, y entonces la medición no está «fuera de
 *    objetivo»: está **sin objetivo**, que es la distinción de
 *    `EstadoControl::PorConfirmar`.
 *
 * 7. **Un periodo, no una fecha.** Una medición registrada el 3 de marzo no dice
 *    a qué trimestre corresponde si se apuntó tarde, y la serie se desordena
 *    sola. De ahí `periodo_inicio`/`periodo_fin` para **a qué** pertenece el dato
 *    y `medida_en` para **cuándo** se tomó, que no son lo mismo. El par
 *    `(indicador, periodo_inicio)` es único: medir dos veces el mismo periodo es
 *    corregir, no acumular.
 *
 * **Lo que NO lleva es trigger de inmutabilidad**, a diferencia de los cuatro
 * registros que sí lo llevan, y conviene decir por qué. Una medición no la firma
 * nadie y no se entrega sola a un auditor; lo que se congela es el acta de la
 * revisión por la dirección que la cita. Un trigger aquí haría imposible
 * corregir un dedazo en una medición manual, que es el caso ordinario. La
 * frontera es otra: **derivar en silencio, prohibido; corregir con autor y
 * traza, permitido** — de eso responde `RegistraTraza`.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`, `auditorias`, `no_conformidades` y
 * `contexto`: enumerando desde el enum, `migrate:fresh` incluye los valores
 * nuevos aunque falte la migración que los añade, y ningún test se pone rojo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ORIGENES = ['calculado', 'manual'];

    /** @var list<string> */
    private const UNIDADES = ['porcentaje', 'recuento', 'dias', 'euros'];

    /** @var list<string> */
    private const SENTIDOS = ['mayor_mejor', 'menor_mejor'];

    /** @var list<string> */
    private const PERIODICIDADES = ['mensual', 'trimestral', 'semestral', 'anual'];

    /**
     * Los cálculos. Cada uno nombra scopes y resúmenes que ya existen.
     *
     * @var list<string>
     */
    private const CALCULOS = [
        'cumplimiento_implantado',
        'implantaciones_pendientes',
        'implantadas_sin_evidencia',
        'madurez_media',
        'evidencias_caducadas',
        'tareas_vencidas',
        'tareas_sin_responsable',
        'no_conformidades_abiertas',
        'no_conformidades_sin_verificar',
        'riesgos_sobre_umbral',
        'activos_sin_cifrar',
        'activos_sin_revisar',
    ];

    public function up(): void
    {
        $origenes = $this->lista(self::ORIGENES);
        $unidades = $this->lista(self::UNIDADES);
        $sentidos = $this->lista(self::SENTIDOS);
        $periodicidades = $this->lista(self::PERIODICIDADES);
        $calculos = $this->lista(self::CALCULOS);

        Schema::create('indicadores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Se cita en actas —«el IND-04 lleva tres trimestres fuera de
            // objetivo»—, así que lo pone la organización y no es el id.
            $table->string('codigo');

            $table->string('nombre');
            $table->text('descripcion')->nullable();

            $table->string('origen')->default('manual');

            /*
             * Las dos mitades del «formula_o_fuente» de § 2.2, separadas porque
             * no son la misma respuesta: una nombra un cálculo que existe en el
             * código y la otra describe un método que ocurre fuera. Cada una es
             * obligatoria exactamente cuando la otra sobra, y lo acopla un
             * `CHECK` en las dos direcciones.
             */
            $table->string('calculo')->nullable();
            $table->text('formula_o_fuente')->nullable();

            /*
             * El marco es opcional y acota los cálculos que se dejan acotar.
             * § 4.14 pide «porcentaje de implantación **por marco**» con esas
             * palabras, y es la pregunta del producto: ISO y ENS avanzan a
             * ritmos distintos y una sola cifra los promedia hasta que no dice
             * nada. Apunta al catálogo global, que no lleva `organizacion_id`
             * (invariante 2), igual que `implantaciones.requisito_id`.
             */
            $table->foreignId('marco_id')->nullable()->constrained('marcos')->nullOnDelete();

            $table->string('unidad')->default('recuento');
            $table->string('sentido')->default('mayor_mejor');
            $table->string('periodicidad')->default('trimestral');

            // El objetivo **vigente**. El que se aplicó a cada periodo viaja
            // sellado en su medición.
            $table->decimal('objetivo', 12, 2)->nullable();

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Se retira, no se borra. Un indicador que deja de medirse conserva
             * su serie, y esa serie es justamente la que explica por qué se dejó
             * de medir. Mismo criterio que `descartada` en tareas: borrar deja el
             * histórico sin rastro de qué se decidió.
             */
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'activo']);
        });

        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_unidad_check CHECK (unidad IN ({$unidades}))");
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_sentido_check CHECK (sentido IN ({$sentidos}))");
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_periodicidad_check CHECK (periodicidad IN ({$periodicidades}))");
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_calculo_valido_check CHECK (calculo IS NULL OR calculo IN ({$calculos}))");

        /*
         * Un indicador calculado sin cálculo no se puede medir; uno manual con
         * cálculo miente sobre de dónde sale su cifra; y uno manual sin método
         * escrito no contesta a la 9.1 b). Las tres, en las dos direcciones.
         */
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_calculado_check CHECK ((origen = 'calculado') = (calculo IS NOT NULL))");
        DB::statement("ALTER TABLE indicadores ADD CONSTRAINT indicadores_manual_check CHECK ((origen = 'manual') = (formula_o_fuente IS NOT NULL AND length(trim(formula_o_fuente)) > 0))");

        Schema::create('mediciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('indicador_id')->constrained('indicadores')->cascadeOnDelete();

            // A qué periodo corresponde el dato…
            $table->date('periodo_inicio');
            $table->date('periodo_fin');

            // …y cuándo se tomó de verdad, que no es lo mismo: una medición de
            // marzo se puede apuntar en abril, y la serie la ordena el periodo.
            $table->timestamp('medida_en');

            $table->decimal('valor', 12, 2);

            /*
             * El denominador, que es lo que convierte un número en una cifra que
             * se puede leer. Van los dos o no va ninguno: «43» sin «de 307» es
             * exactamente lo que este producto le reprocha a la hoja de cálculo.
             */
            $table->unsignedInteger('numerador')->nullable();
            $table->unsignedInteger('denominador')->nullable();

            // El objetivo que estaba puesto el día que se cerró el periodo.
            $table->decimal('objetivo', 12, 2)->nullable();

            $table->string('origen');
            $table->text('nota')->nullable();

            $table->foreignId('registrada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Medir dos veces el mismo periodo es corregir, no acumular.
            $table->unique(['indicador_id', 'periodo_inicio']);
            $table->index(['organizacion_id', 'indicador_id', 'periodo_inicio']);
        });

        DB::statement("ALTER TABLE mediciones ADD CONSTRAINT mediciones_origen_check CHECK (origen IN ({$origenes}))");
        DB::statement('ALTER TABLE mediciones ADD CONSTRAINT mediciones_periodo_check CHECK (periodo_fin >= periodo_inicio)');

        /*
         * Un numerador exige denominador, pero **no al revés**, y la asimetría es
         * real: una fracción necesita los dos —«43 de 307»— y una media necesita
         * sólo el segundo —«3,2 sobre 48 requisitos valorados»—. Es el reparto que
         * ya tiene `ResumenCumplimiento::madurez()`, que devuelve `media` y
         * `evaluadas` y ningún numerador. Exigir los dos dejaría las medias sin
         * denominador, que es justo lo que este producto no se permite.
         */
        DB::statement('ALTER TABLE mediciones ADD CONSTRAINT mediciones_fraccion_check CHECK (numerador IS NULL OR denominador IS NOT NULL)');

        // Y un denominador a cero no es un denominador: es una división que
        // revienta. «No hay de dónde contar» se escribe con un nulo.
        DB::statement('ALTER TABLE mediciones ADD CONSTRAINT mediciones_denominador_check CHECK (denominador IS NULL OR denominador > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mediciones');
        Schema::dropIfExists('indicadores');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
