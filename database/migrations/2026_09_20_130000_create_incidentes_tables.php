<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de incidentes: § 4.10 y `op.exp.7`.
 *
 * **El segundo de los dos módulos que muerden en categoría básica**, junto a las
 * personas: `op.exp.7` es exigible desde el primer día y no tenía dónde
 * registrarse. Tres tablas, y cuatro decisiones que no se ven leyendo el esquema.
 *
 * 1. **Las cinco dimensiones van en cinco columnas booleanas**, no en filas ni en
 *    JSONB. Es el mismo reparto que ya hizo `activos` con su valoración propia y
 *    por el mismo motivo: son cinco, no van a ser seis, y cinco columnas se
 *    consultan y se indexan. «Qué se vio afectado» es la primera pregunta de un
 *    informe de incidente y la que decide si hay que notificar.
 *
 * 2. **Las dos notificaciones van en columnas y no en una tabla**, como dibuja la
 *    § 2.2: son dos destinatarios fijados por ley, y una tabla de notificaciones
 *    con dos filas posibles es una tabla que nadie consulta. **Declarado**: un
 *    tercer supervisor —NIS2, o un regulador sectorial— sí pedirá tabla, y
 *    entonces se migra.
 *
 * 3. **Sólo hay reloj donde la ley pone un número.** `notificado_aepd_en` cuenta
 *    contra las 72 h del art. 33.1 del RGPD, y **sólo si hay datos personales de
 *    por medio**. Para el CCN-CERT se registra si es notificable y cuándo se
 *    notificó, y **no hay cuenta atrás**: el RD 311/2022 dice «sin dilación» y no
 *    fija horas. Poner un número sería una opinión de la herramienta disfrazada de
 *    plazo legal, que es exactamente lo que el producto se niega a hacer con el
 *    riesgo residual.
 *
 * 4. **Cerrar exige lección aprendida**, y es el argumento del módulo: `op.exp.7`
 *    pide aprender del incidente y es el paso que todo el mundo se salta — el
 *    mismo razonamiento por el que la verificación de eficacia es un estado y no
 *    una casilla en el § 4.13. Va en `CHECK` y además en `CambiarEstadoIncidente`,
 *    porque la regla vale igual para un importador.
 *
 * Los literales de los `CHECK` van **escritos a mano y no desde los enums**: con
 * `cases()`, una base recién migrada admitiría cualquier valor nuevo aunque
 * faltara su migración, y ningún test se pondría rojo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['abierto', 'en_tratamiento', 'resuelto', 'cerrado'];

    /**
     * Los estados en los que el incidente ya no está vivo.
     *
     * `resuelto` **no** entra: se ha restablecido el servicio y todavía falta la
     * lección aprendida, que es justo lo que este módulo existe para no perder.
     *
     * @var list<string>
     */
    private const CERRADOS = ['cerrado'];

    /**
     * Las clases de nivel superior de la taxonomía CCN-STIC 817.
     *
     * **Sin contrastar celda a celda contra la guía**, igual que las dimensiones
     * de las 56 amenazas de MAGERIT: se usan como clasificación de trabajo y los
     * subtipos de la 817 no están cargados. Va declarado en el módulo.
     *
     * @var list<string>
     */
    private const CLASIFICACIONES = [
        'contenido_abusivo',
        'contenido_danino',
        'obtencion_informacion',
        'intento_intrusion',
        'intrusion',
        'disponibilidad',
        'compromiso_informacion',
        'fraude',
        'vulnerable',
        'otros',
    ];

    /** @var list<string> */
    private const PELIGROSIDADES = ['baja', 'media', 'alta', 'muy_alta', 'critica'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $cerrados = $this->lista(self::CERRADOS);
        $clasificaciones = $this->lista(self::CLASIFICACIONES);
        $peligrosidades = $this->lista(self::PELIGROSIDADES);

        Schema::create('incidentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');
            $table->text('descripcion');

            /*
             * El sistema es **opcional**, al revés que en una auditoría. Un
             * incidente se detecta antes de saber a qué alcance pertenece —un
             * correo fraudulento a toda la organización no es de ningún sistema—,
             * y obligarlo haría que quien lo apunta a las tres de la mañana
             * eligiera el que menos mal le suena.
             */
            $table->foreignId('sistema_id')->nullable()->constrained('sistemas')->nullOnDelete();

            $table->string('clasificacion')->default('otros');
            $table->string('peligrosidad')->default('baja');

            /*
             * **Cuándo empezó y cuándo se detectó son dos fechas.** La diferencia
             * entre las dos es la primera cifra que un informe de incidente
             * enseña, y con una sola columna se pierde. `fecha_inicio` es nula
             * cuando no se sabe, que es lo normal al principio.
             */
            $table->dateTime('fecha_deteccion');
            $table->dateTime('fecha_inicio')->nullable();

            // Las cinco dimensiones del Anexo I. Ver el punto 1 de la cabecera.
            $table->boolean('afecta_confidencialidad')->default(false);
            $table->boolean('afecta_integridad')->default(false);
            $table->boolean('afecta_disponibilidad')->default(false);
            $table->boolean('afecta_autenticidad')->default(false);
            $table->boolean('afecta_trazabilidad')->default(false);

            $table->text('impacto')->nullable();

            // Lo que se hizo para parar el golpe, que no es la acción correctiva:
            // ésa cuelga de la no conformidad, si es que la hay.
            $table->text('acciones_contencion')->nullable();

            /*
             * El paso que todo el mundo se salta, y por eso cerrar lo exige. Ver
             * el punto 4 de la cabecera.
             */
            $table->text('leccion_aprendida')->nullable();

            $table->string('estado')->default('abierto');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('fecha_cierre')->nullable();

            /*
             * Las dos notificaciones. **Notificable y notificado son dos campos y
             * no uno**: «no había que notificar» y «había que notificar y no se
             * hizo» son la misma columna vacía si se colapsan, y la segunda es un
             * incumplimiento y la primera no.
             */
            $table->boolean('notificable_aepd')->default(false);
            $table->dateTime('notificado_aepd_en')->nullable();

            $table->boolean('notificable_ccn_cert')->default(false);
            $table->dateTime('notificado_ccn_cert_en')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_deteccion']);
            $table->index(['organizacion_id', 'notificable_aepd', 'notificado_aepd_en']);
        });

        DB::statement("ALTER TABLE incidentes ADD CONSTRAINT incidentes_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE incidentes ADD CONSTRAINT incidentes_clasificacion_check CHECK (clasificacion IN ({$clasificaciones}))");
        DB::statement("ALTER TABLE incidentes ADD CONSTRAINT incidentes_peligrosidad_check CHECK (peligrosidad IN ({$peligrosidades}))");
        DB::statement('ALTER TABLE incidentes ADD CONSTRAINT incidentes_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE incidentes ADD CONSTRAINT incidentes_descripcion_check CHECK (length(trim(descripcion)) > 0)');

        // En las dos direcciones, como en tareas, auditorías, no conformidades y
        // mejoras: cerrado sin fecha no se puede fechar después sin inventársela,
        // y abierto con fecha es una contradicción.
        DB::statement("ALTER TABLE incidentes ADD CONSTRAINT incidentes_cierre_coherente_check CHECK ((estado IN ({$cerrados})) = (fecha_cierre IS NOT NULL))");

        /*
         * **Cerrar exige lección aprendida.** En una sola dirección: escribirla
         * antes de cerrar es lo que se espera de quien va tomando notas mientras
         * lo resuelve.
         */
        DB::statement("ALTER TABLE incidentes ADD CONSTRAINT incidentes_leccion_check CHECK (estado <> 'cerrado' OR (leccion_aprendida IS NOT NULL AND length(trim(leccion_aprendida)) > 0))");

        // No se detecta lo que todavía no ha empezado.
        DB::statement('ALTER TABLE incidentes ADD CONSTRAINT incidentes_orden_fechas_check CHECK (fecha_inicio IS NULL OR fecha_inicio <= fecha_deteccion)');

        /*
         * Notificar lo que no era notificable es un dato contradictorio, no una
         * cautela. Las dos, en una sola dirección: notificable y sin notificar es
         * el caso vivo —el reloj corriendo— y es lo que el módulo tiene que poder
         * enseñar en rojo.
         */
        DB::statement('ALTER TABLE incidentes ADD CONSTRAINT incidentes_aepd_check CHECK (notificado_aepd_en IS NULL OR notificable_aepd)');
        DB::statement('ALTER TABLE incidentes ADD CONSTRAINT incidentes_ccn_check CHECK (notificado_ccn_cert_en IS NULL OR notificable_ccn_cert)');

        /*
         * Los activos afectados.
         *
         * § 2.2 dice `activos_afectados` en plural, y N:M es lo correcto: un
         * cifrado por ransomware toca treinta equipos y un incidente por activo
         * daría treinta incidentes donde hay uno que gestionar. Mismo argumento
         * aritmético que en riesgo ↔ activo.
         *
         * Con `organizacion_id`, como `implantacion_tarea`: no es redundante con
         * las dos claves, es lo que la mete dentro de las tres capas. Y si se
         * olvidara, `RlsDeclaradaTest` no diría nada, porque sólo mira las tablas
         * que ya tienen la columna.
         */
        Schema::create('incidente_activo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('incidente_id')->constrained('incidentes')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incidente_id', 'activo_id']);
        });

        /*
         * El histórico (invariante 7). La pregunta del auditor no es «¿está
         * cerrado?», es «¿cuánto se tardó en contenerlo?».
         */
        Schema::create('incidente_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('incidente_id')->constrained('incidentes')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['incidente_id', 'created_at']);
        });

        DB::statement("ALTER TABLE incidente_transiciones ADD CONSTRAINT incidente_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE incidente_transiciones ADD CONSTRAINT incidente_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('incidente_transiciones');
        Schema::dropIfExists('incidente_activo');
        Schema::dropIfExists('incidentes');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(
            static fn (string $valor): string => "'".str_replace("'", "''", $valor)."'",
            $valores,
        ));
    }
};
