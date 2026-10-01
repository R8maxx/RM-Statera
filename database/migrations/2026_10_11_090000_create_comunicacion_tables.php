<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La comunicación del SGSI: la cláusula 7.4 de ISO 27001.
 *
 * La norma pide determinar qué se comunica, cuándo, a quién, quién lo hace y
 * cómo. Son **dos cosas distintas** y por eso son dos tablas:
 *
 * - **`comunicaciones_previstas`** — el plan: lo que la organización se ha
 *   propuesto comunicar, con su cadencia. «Informe trimestral de seguridad a la
 *   dirección», «aviso anual de la política a todo el personal».
 * - **`comunicaciones`** — lo que de verdad se comunicó, y lo que se **recibió**.
 *   Lo emitido cumple el plan; lo recibido —una queja, una encuesta, una
 *   sugerencia— es la retroalimentación de las partes interesadas que la 9.3.2 e)
 *   pide revisar, y que hasta aquí el acta declaraba que no se registraba.
 *
 * **Es el patrón de los compromisos del § 4.16**, y se copia y no se reutiliza:
 * una comunicación prevista no es una obligación del catálogo, tiene destinatarios
 * y canal, y lleva su propia `Fuente`. Lo que sí se comparte es la regla: la
 * periodicidad es un **entero de meses** que lee `Cadencia`, la próxima fecha **se
 * deriva** del último `cubre_hasta` o de `computa_desde`, y `cubre_hasta` **se
 * congela** al registrar.
 *
 * Los `CHECK` se construyen desde constantes de esta migración y no desde los
 * enums, que es el patrón del resto.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CANALES = ['correo', 'reunion', 'intranet', 'formacion', 'documento', 'web', 'telefono', 'otro'];

    /** @var list<string> */
    private const SENTIDOS = ['emitida', 'recibida'];

    /** @var list<string> */
    private const TIPOS_RECIBIDA = ['queja', 'sugerencia', 'consulta', 'encuesta', 'felicitacion', 'otra'];

    public function up(): void
    {
        $canales = $this->lista(self::CANALES);
        $sentidos = $this->lista(self::SENTIDOS);
        $tiposRecibida = $this->lista(self::TIPOS_RECIBIDA);

        Schema::create('comunicaciones_previstas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');

            // Qué se comunica.
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            // Cómo.
            $table->string('canal');

            // Quién lo comunica.
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Cuándo. Nula es «cuando proceda»: un aviso que se da al cambiar la
             * política no tiene cadencia, y no entra en el calendario. Inventarle
             * una sería pintar un plazo al que nadie se comprometió.
             */
            $table->unsignedSmallInteger('periodicidad_meses')->nullable();
            $table->date('computa_desde')->nullable();

            // A quién, cuando no es una parte interesada registrada:
            // «todo el personal de la sede», «los usuarios del servicio».
            $table->text('destinatarios_otros')->nullable();

            $table->date('retirada_en')->nullable();
            $table->text('motivo_retirada')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'retirada_en']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement("ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_canal_check CHECK (canal IN ({$canales}))");
        DB::statement('ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_titulo_check CHECK (length(trim(titulo)) > 0)');
        DB::statement('ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_periodicidad_check CHECK (periodicidad_meses IS NULL OR periodicidad_meses BETWEEN 1 AND 120)');

        // Con cadencia hace falta desde cuándo cuenta, y sin ella no significa
        // nada: en las dos direcciones.
        DB::statement('ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_computa_check CHECK ((periodicidad_meses IS NULL) = (computa_desde IS NULL))');
        DB::statement('ALTER TABLE comunicaciones_previstas ADD CONSTRAINT comunicaciones_previstas_retirada_check CHECK ((retirada_en IS NULL) = (motivo_retirada IS NULL OR length(trim(motivo_retirada)) = 0))');

        /*
         * A quién, cuando es una parte interesada registrada (§ 4.1). La pivote
         * lleva `organizacion_id` como todas: es lo que la mete en las tres capas.
         * Cascada por los dos lados: una parte interesada no se borra —se da de
         * baja en un análisis—, así que esto sólo salta al borrar la previsión.
         */
        Schema::create('comunicacion_prevista_parte_interesada', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            // Nombres a mano: los generados pasan de 63 caracteres, PostgreSQL los
            // recorta y la clave foránea y el índice único acaban llamándose igual.
            $table->foreignId('comunicacion_prevista_id')
                ->constrained('comunicaciones_previstas', indexName: 'cppi_prevista_fk')
                ->cascadeOnDelete();
            $table->foreignId('parte_interesada_id')
                ->constrained('partes_interesadas', indexName: 'cppi_parte_fk')
                ->cascadeOnDelete();

            $table->unique(['comunicacion_prevista_id', 'parte_interesada_id'], 'cppi_unica');
            $table->index(['organizacion_id', 'parte_interesada_id'], 'cppi_organizacion_parte_idx');
        });

        Schema::create('comunicaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('sentido');

            /*
             * La previsión que cumple, cuando cumple una. Sólo lo emitido: lo
             * recibido no cumple ningún plan. `nullOnDelete` y no cascada: borrar
             * una previsión no puede llevarse por delante lo que de verdad se
             * comunicó.
             */
            $table->foreignId('comunicacion_prevista_id')->nullable()->constrained('comunicaciones_previstas')->nullOnDelete();

            $table->date('fecha');

            /*
             * Hasta cuándo cubre la previsión periódica. **Se congela al
             * registrar**, con la cadencia vigente entonces: subirla de anual a
             * semestral no repinta como fuera de plazo lo que ya estaba al día.
             */
            $table->date('cubre_hasta')->nullable();

            $table->string('asunto');
            $table->text('resumen')->nullable();
            $table->string('canal');

            // De quién o a quién, cuando es una parte interesada registrada.
            $table->foreignId('parte_interesada_id')->nullable()->constrained('partes_interesadas')->nullOnDelete();

            // Sólo lo recibido: qué tipo de retroalimentación es (9.3.2 e).
            $table->string('tipo_recibida')->nullable();

            // Sólo lo recibido: qué se contestó o qué se hizo con ello.
            $table->text('respuesta')->nullable();

            // La prueba, que convive con el registro: como en los cumplimientos.
            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            $table->foreignId('registrada_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'sentido', 'fecha']);
            $table->index(['comunicacion_prevista_id', 'cubre_hasta']);
        });

        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_sentido_check CHECK (sentido IN ({$sentidos}))");
        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_canal_check CHECK (canal IN ({$canales}))");
        DB::statement('ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_asunto_check CHECK (length(trim(asunto)) > 0)');
        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_tipo_recibida_check CHECK (tipo_recibida IS NULL OR tipo_recibida IN ({$tiposRecibida}))");

        // Lo recibido lleva tipo y lo emitido no, en las dos direcciones.
        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_tipo_coherente_check CHECK ((sentido = 'recibida') = (tipo_recibida IS NOT NULL))");

        // Sólo lo emitido cumple un plan, y sólo lo que cumple un plan cubre.
        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_prevista_emitida_check CHECK (comunicacion_prevista_id IS NULL OR sentido = 'emitida')");
        DB::statement('ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_cubre_check CHECK (cubre_hasta IS NULL OR (comunicacion_prevista_id IS NOT NULL AND cubre_hasta > fecha))');

        // La respuesta es de lo recibido: a lo emitido no se le contesta aquí.
        DB::statement("ALTER TABLE comunicaciones ADD CONSTRAINT comunicaciones_respuesta_check CHECK (respuesta IS NULL OR sentido = 'recibida')");
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicaciones');
        Schema::dropIfExists('comunicacion_prevista_parte_interesada');
        Schema::dropIfExists('comunicaciones_previstas');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
