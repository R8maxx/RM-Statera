<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El contexto de la organización: § 4.1, y las cláusulas 4.1, 4.2 y 4.3 de ISO.
 *
 * Es el único de los diecinueve módulos que no estaba asignado a ninguna fase, y
 * el que le falta al § 4.15: la cláusula 9.3 pide «cambios de contexto» como
 * entrada obligatoria de la revisión por la dirección, y hoy no hay de dónde
 * sacarla. Los requisitos `4.1`, `4.2` y `4.3` llevan en el catálogo desde el
 * principio, con su implantación y su responsable, esperando algo que los
 * respalde.
 *
 * **La 4.3 no se toca, porque ya estaba hecha.** `sistemas.alcance_declarado` y
 * `sistemas.exclusiones_justificadas` existen desde la primera migración y ya se
 * imprimen en la portada de los cuatro documentos. Lo que este módulo le añade no
 * es una tabla: es histórico, congelándolas en la instantánea del análisis.
 *
 * Cuatro decisiones que no se deducen del esquema:
 *
 * 1. **El análisis es la unidad versionada; las cuestiones y las partes tienen
 *    identidad estable.** Copiar las cuestiones enteras en cada análisis —el
 *    patrón literal de `riesgo_valoraciones`— rompería los vínculos: un riesgo
 *    apuntaría a la cuestión de marzo y en octubre apuntaría a una fila muerta.
 *    Así que viven, con `analisis_alta_id` y `analisis_baja_id`, y lo que se
 *    congela es la **instantánea** del análisis al aprobarse. Mismo razonamiento
 *    que `riesgo_valoraciones.salvaguardas` y `documento_versiones.instantanea`:
 *    sin ella la fila miente en cuanto alguien edite una cuestión.
 *
 * 2. **`vigente` no es columna: es `estado = 'aprobado'`**, y lo garantiza un
 *    índice único parcial, como el borrador de un documento. Una bandera aparte
 *    sería el mismo dato en dos sitios que pueden desincronizarse.
 *
 * 3. **El cambio climático es una pregunta con respuesta obligatoria.** La
 *    enmienda 1:2024 no pide apuntar cuestiones climáticas: pide **determinar si**
 *    el cambio climático es pertinente. Con una casilla suelta, «no lo hemos
 *    mirado» y «lo hemos mirado y no aplica» serían indistinguibles — que es
 *    justo lo que el auditor pregunta. De ahí `clima_pertinente` +
 *    `clima_justificacion` y un `CHECK` que impide aprobar sin contestar. Mismo
 *    criterio que `rechazado` exige motivo.
 *
 * 4. **El origen y el signo de una cuestión NO se guardan.** Fortaleza y
 *    debilidad son internas; oportunidad y amenaza, externas. Eso es la
 *    definición de un DAFO, no un dato: guardarlo sería la misma información en
 *    dos columnas que pueden discrepar. Van derivados en `TipoCuestion`.
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
    private const ESTADOS_ANALISIS = ['borrador', 'aprobado', 'obsoleto'];

    /** Los estados en los que el análisis ya está firmado y congelado. */
    private const FIRMADOS = "'aprobado', 'obsoleto'";

    /** @var list<string> */
    private const TIPOS_CUESTION = ['fortaleza', 'debilidad', 'oportunidad', 'amenaza'];

    /** @var list<string> */
    private const MATERIAS = [
        'legal_regulatorio', 'tecnologico', 'economico', 'organizativo',
        'social', 'ambiental', 'competitivo', 'contractual',
    ];

    /** @var list<string> */
    private const TIPOS_PARTE = [
        'cliente', 'empleado', 'direccion', 'proveedor',
        'regulador', 'socio', 'sociedad', 'accionista',
    ];

    /** @var list<string> */
    private const AMBITOS_PARTE = ['interno', 'externo'];

    /** @var list<string> */
    private const NATURALEZAS = ['legal', 'contractual', 'expectativa'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS_ANALISIS);
        $firmados = self::FIRMADOS;
        $tiposCuestion = $this->lista(self::TIPOS_CUESTION);
        $materias = $this->lista(self::MATERIAS);
        $tiposParte = $this->lista(self::TIPOS_PARTE);
        $ambitosParte = $this->lista(self::AMBITOS_PARTE);
        $naturalezas = $this->lista(self::NATURALEZAS);

        /*
         * El análisis: una revisión entera del contexto, con su fecha y su firma.
         *
         * Es de la organización y no de un sistema, a diferencia de casi todo lo
         * que hay en este esquema. Las cuestiones internas y externas y las partes
         * interesadas son de la organización entera; lo que sí es por sistema es
         * el alcance, y ése vive donde ya vivía.
         */
        Schema::create('analisis_contexto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            /*
             * Nulo es el borrador, exactamente como en `documento_versiones`: hay
             * uno como mucho y se retoca cuantas veces haga falta. El número se
             * gasta al aprobar, y un hueco en la numeración es una pregunta del
             * auditor.
             */
            $table->unsignedInteger('numero')->nullable();

            // Cuándo se hizo el análisis, que no es cuándo se aprobó: el taller
            // es en enero y la dirección lo firma en febrero.
            $table->date('fecha_analisis');

            $table->string('estado')->default('borrador');

            /*
             * La enmienda 1:2024, punto 3 de la cabecera. Nulo sólo mientras es
             * borrador: el `CHECK` no deja aprobar sin contestar.
             */
            $table->boolean('clima_pertinente')->nullable();
            $table->text('clima_justificacion')->nullable();

            // El razonamiento de quien lo hizo: cómo se llegó a esto, quién
            // participó en el taller, qué fuentes se miraron.
            $table->text('nota')->nullable();

            /*
             * El DAFO, las partes, sus requisitos y el alcance de cada sistema tal
             * como estaban el día de la firma. **No es redundante con las tablas**:
             * sin ella, editar una cuestión en 2027 cambiaría lo que dice el
             * análisis de 2026, y «¿qué cambió entre uno y otro?» no se contesta.
             * Es el mismo argumento que ya está escrito para el `.docx`.
             */
            $table->jsonb('instantanea')->nullable();

            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'numero']);
            $table->index(['organizacion_id', 'estado']);
        });

        DB::statement("ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_estado_check CHECK (estado IN ({$estados}))");

        /*
         * Un borrador como mucho y un aprobado como mucho, los dos por índice
         * único parcial. Es lo que hace que «el análisis vigente» sea una consulta
         * y no una convención que alguien tiene que respetar.
         */
        DB::statement('CREATE UNIQUE INDEX analisis_contexto_borrador_unico ON analisis_contexto (organizacion_id) WHERE numero IS NULL');
        DB::statement("CREATE UNIQUE INDEX analisis_contexto_vigente_unico ON analisis_contexto (organizacion_id) WHERE estado = 'aprobado'");

        // Un borrador no tiene número y un firmado sí, en las dos direcciones.
        DB::statement("ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_numero_check CHECK ((estado IN ({$firmados})) = (numero IS NOT NULL))");

        /*
         * La firma, en las dos direcciones. Aquí sí se puede exigir a `obsoleto`,
         * a diferencia de `documento_versiones`: allí había filas emitidas bajo el
         * modelo anterior y rellenarles el firmante habría sido fabricar una
         * firma. Este módulo nace con el flujo puesto.
         */
        DB::statement("ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_firma_check CHECK ((estado IN ({$firmados})) = (aprobado_en IS NOT NULL))");
        DB::statement('ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_firmante_check CHECK ((aprobado_por_id IS NULL) = (aprobado_en IS NULL))');

        // Punto 3 de la cabecera: no se aprueba sin contestar a la pregunta del
        // cambio climático, y «no es pertinente» es una respuesta perfectamente
        // válida siempre que venga razonada.
        DB::statement("ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_clima_check CHECK (estado = 'borrador' OR (clima_pertinente IS NOT NULL AND length(trim(coalesce(clima_justificacion, ''))) > 0))");

        // Y no se aprueba sin congelar: un análisis firmado sin instantánea es un
        // análisis que cambia solo.
        DB::statement("ALTER TABLE analisis_contexto ADD CONSTRAINT analisis_contexto_instantanea_check CHECK (estado = 'borrador' OR instantanea IS NOT NULL)");

        /*
         * Las cuestiones internas y externas: el DAFO. Cláusula 4.1.
         */
        Schema::create('cuestiones_contexto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            /*
             * Una sola secuencia, sin prefijo por tipo. Un «D-03» que alguien
             * reclasifica como oportunidad se queda con la D delante para
             * siempre, y el código de una cuestión se cita en actas.
             */
            $table->string('codigo');

            $table->string('tipo');
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            /*
             * De qué va la cuestión: legal, tecnológica, económica…
             *
             * Se llama `materia` y no `ambito` a propósito, porque `ambito` ya es
             * el interno/externo que comparten las cuestiones y las partes
             * interesadas. Dos columnas llamadas igual con dos ejes distintos es
             * cómo se acaba filtrando por lo que no era.
             */
            $table->string('materia');

            // Cuáles de las cuestiones son las del cambio climático, para que la
            // declaración del análisis pueda enseñarlas en vez de afirmarlo.
            $table->boolean('es_climatica')->default(false);

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * De qué análisis nace y en cuál se retira. **`restrictOnDelete` en
             * los dos**: un análisis que dio de alta cuestiones no se borra, y
             * borrarlo dejaría las cuestiones sin saber desde cuándo existen.
             */
            $table->foreignId('analisis_alta_id')->constrained('analisis_contexto')->restrictOnDelete();
            $table->foreignId('analisis_baja_id')->nullable()->constrained('analisis_contexto')->restrictOnDelete();
            $table->text('motivo_baja')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'tipo']);
            $table->index(['organizacion_id', 'analisis_baja_id']);
        });

        DB::statement("ALTER TABLE cuestiones_contexto ADD CONSTRAINT cuestiones_contexto_tipo_check CHECK (tipo IN ({$tiposCuestion}))");
        DB::statement("ALTER TABLE cuestiones_contexto ADD CONSTRAINT cuestiones_contexto_materia_check CHECK (materia IN ({$materias}))");
        DB::statement('ALTER TABLE cuestiones_contexto ADD CONSTRAINT cuestiones_contexto_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE cuestiones_contexto ADD CONSTRAINT cuestiones_contexto_titulo_check CHECK (length(trim(titulo)) > 0)');

        // Retirar una cuestión es una decisión y se explica, como descartar una
        // tarea. En las dos direcciones: un motivo sin baja es ruido.
        DB::statement("ALTER TABLE cuestiones_contexto ADD CONSTRAINT cuestiones_contexto_baja_check CHECK ((analisis_baja_id IS NOT NULL) = (length(trim(coalesce(motivo_baja, ''))) > 0))");

        /*
         * Las partes interesadas. Cláusula 4.2.
         */
        Schema::create('partes_interesadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('nombre');
            $table->string('tipo');

            /*
             * Interno o externo, **explícito y no derivado del tipo**, a
             * diferencia del origen de una cuestión: un empleado es interno y un
             * regulador externo, pero un socio o un accionista pueden ser
             * cualquiera de los dos según cómo esté montada la organización.
             * Deducirlo sería acertar en seis casos de ocho.
             */
            $table->string('ambito');

            $table->text('descripcion')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('analisis_alta_id')->constrained('analisis_contexto')->restrictOnDelete();
            $table->foreignId('analisis_baja_id')->nullable()->constrained('analisis_contexto')->restrictOnDelete();
            $table->text('motivo_baja')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'tipo']);
            $table->index(['organizacion_id', 'analisis_baja_id']);
        });

        DB::statement("ALTER TABLE partes_interesadas ADD CONSTRAINT partes_interesadas_tipo_check CHECK (tipo IN ({$tiposParte}))");
        DB::statement("ALTER TABLE partes_interesadas ADD CONSTRAINT partes_interesadas_ambito_check CHECK (ambito IN ({$ambitosParte}))");
        DB::statement('ALTER TABLE partes_interesadas ADD CONSTRAINT partes_interesadas_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE partes_interesadas ADD CONSTRAINT partes_interesadas_nombre_check CHECK (length(trim(nombre)) > 0)');
        DB::statement("ALTER TABLE partes_interesadas ADD CONSTRAINT partes_interesadas_baja_check CHECK ((analisis_baja_id IS NOT NULL) = (length(trim(coalesce(motivo_baja, ''))) > 0))");

        /*
         * Lo que cada parte interesada espera o exige.
         *
         * El nombre lleva «interesados» y no es cosmético: `requisitos` a secas es
         * el catálogo normativo, que es global y no lleva `organizacion_id`
         * (invariante 2). Dos tablas de requisitos con el mismo nombre en dos
         * ámbitos distintos es cómo se acaba con una consulta cruzando la frontera
         * sin que nadie lo vea.
         */
        Schema::create('requisitos_interesados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('parte_interesada_id')->constrained('partes_interesadas')->cascadeOnDelete();

            $table->text('descripcion');

            /*
             * Legal, contractual o expectativa, y la diferencia importa: lo legal
             * y lo contractual obligan, y una expectativa es algo que conviene
             * atender y que nadie puede exigir. Meterlas en el mismo saco es cómo
             * se acaba tratando una expectativa como un incumplimiento.
             */
            $table->string('naturaleza');

            // La otra mitad de la enmienda 1:2024: los requisitos de las partes
            // interesadas relacionados con el cambio climático.
            $table->boolean('es_climatico')->default(false);

            // «RD 311/2022, art. 12», «contrato marco con X, cláusula 8». Es lo
            // que hace rastreable un requisito legal.
            $table->string('referencia')->nullable();

            $table->text('como_se_atiende')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'parte_interesada_id']);
            $table->index(['organizacion_id', 'naturaleza']);
        });

        DB::statement("ALTER TABLE requisitos_interesados ADD CONSTRAINT requisitos_interesados_naturaleza_check CHECK (naturaleza IN ({$naturalezas}))");
        DB::statement('ALTER TABLE requisitos_interesados ADD CONSTRAINT requisitos_interesados_descripcion_check CHECK (length(trim(descripcion)) > 0)');

        /*
         * Las tres pivotes, y las tres con `organizacion_id`.
         *
         * No es redundante con las dos claves: es lo que las mete dentro de las
         * tres capas de aislamiento. Y ojo —**si se olvidara, `RlsDeclaradaTest`
         * no diría nada**, porque sólo interroga a las tablas que ya tienen la
         * columna. Es la misma nota que llevan `implantacion_tarea` y
         * `no_conformidad_tarea`.
         */

        /*
         * Una amenaza del DAFO —«dependemos de un solo proveedor de nube»— es de
         * dónde sale un riesgo del § 4.3. N:M porque una cuestión puede abrir
         * varios riesgos y un riesgo puede venir de dos cuestiones a la vez.
         */
        Schema::create('cuestion_riesgo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('cuestion_contexto_id')->constrained('cuestiones_contexto')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cuestion_contexto_id', 'riesgo_id']);
            $table->index(['organizacion_id', 'riesgo_id']);
        });

        /*
         * El trabajo que sale de una cuestión. La tarea es de pleno derecho —con
         * su responsable, su plazo y su coste—, como ya se decidió para las
         * acciones correctivas.
         */
        Schema::create('cuestion_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('cuestion_contexto_id')->constrained('cuestiones_contexto')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cuestion_contexto_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        /*
         * **Apunta a `implantaciones` y no a `requisitos`**, igual que las
         * salvaguardas de un riesgo y por el mismo motivo: «el ENS pide cifrado»
         * y «lo tenemos puesto en este sistema» no son la misma afirmación, y lo
         * que contesta a «¿cómo atendéis lo que os exige este regulador?» es la
         * segunda.
         */
        Schema::create('implantacion_requisito_interesado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('requisito_interesado_id')->constrained('requisitos_interesados')->cascadeOnDelete();
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['requisito_interesado_id', 'implantacion_id'], 'req_interesado_implantacion_unica');
            $table->index(['organizacion_id', 'implantacion_id'], 'req_interesado_implantacion_indice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implantacion_requisito_interesado');
        Schema::dropIfExists('cuestion_tarea');
        Schema::dropIfExists('cuestion_riesgo');
        Schema::dropIfExists('requisitos_interesados');
        Schema::dropIfExists('partes_interesadas');
        Schema::dropIfExists('cuestiones_contexto');
        Schema::dropIfExists('analisis_contexto');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
