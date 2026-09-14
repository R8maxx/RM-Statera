<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El análisis de riesgos: § 4.3 de la especificación y primera pieza de la fase 2.
 *
 * Cinco tablas, y tres decisiones que se apartan de lo que dibuja § 2.2:
 *
 * 1. **`metodologias_riesgo` existe.** § 2.2 no la menciona: da por hecho que las
 *    escalas son universales. No lo son. ISO 27001 6.1.2 a) exige que sea la
 *    ORGANIZACIÓN la que fije sus criterios de aceptación, y el auditor pide el
 *    papel firmado. En `config/` —que es donde vive la obsolescencia, porque el
 *    fin de soporte de Ubuntu sí es un hecho del mundo— un despliegue cambiaría
 *    la escala retroactivamente para todo el histórico y sin dejar constancia.
 *
 * 2. **`activo_riesgo` es N:M**, y § 2.2 dibuja un `activo_id` singular. «Robo de
 *    un portátil» es UN riesgo sobre treinta portátiles: con clave singular, o se
 *    crean treinta riesgos —y el indicador de § 4.14 cuenta treinta donde hay una
 *    cosa que decidir, que es el argumento aritmético que dejó las subtareas
 *    fuera de `tareas`— o se apunta a uno arbitrario y los otros veintinueve son
 *    invisibles. Es el mismo patrón que `activo_sistema` y por el mismo motivo:
 *    duplicar el registro para que quepa en dos sitios es volver a las hojas de
 *    cálculo duplicadas.
 *
 * 3. **La valoración va en su propia tabla y no en columnas del riesgo.** § 2.2
 *    mete probabilidad, impacto y residual dentro de `riesgos`; una fila no
 *    sostiene un histórico, y la propia especificación pide «reevaluación
 *    periódica con histórico comparable». Es la misma corrección que ya se le
 *    hizo a la tabla única de documentos.
 *
 * Y una que sí sigue la letra: el riesgo residual es un dato DECLARADO, no
 * calculado. No existe función publicada de (estado, madurez) a riesgo residual,
 * así que cualquiera que inventáramos sería una opinión de la herramienta
 * disfrazada de cálculo — y ISO 6.1.3 f) exige que lo apruebe el propietario del
 * riesgo, que no puede aprobar algo que dedujo la máquina. Lo derivado se calcula
 * al vuelo en `CoberturaSalvaguardas` y se enseña al lado, exactamente como la
 * valoración efectiva de un activo se enseña junto a la propia.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const DECISIONES = ['mitigar', 'aceptar', 'transferir', 'evitar'];

    public function up(): void
    {
        $decisiones = $this->lista(self::DECISIONES);

        Schema::create('metodologias_riesgo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('nombre');
            $table->string('referencia')->nullable()->comment('MAGERIT v3, ISO/IEC 27005:2022');

            /*
             * Las dos escalas como lista de escalones con etiqueta y descripción.
             * No son tabla propia porque nunca se consultan por separado: se leen
             * enteras para pintar la matriz y se congelan enteras en cada
             * valoración.
             */
            $table->jsonb('escala_probabilidad');
            $table->jsonb('escala_impacto');

            $table->smallInteger('umbral_aceptacion');
            $table->smallInteger('umbral_critico');
            $table->smallInteger('periodicidad_revision_meses')->default(12);

            $table->foreignId('aprobada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('aprobada_en')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            // Una organización no tiene dos apetitos de riesgo. Si algún día los
            // tuviera por sistema, se añade `sistema_id` nullable y la fila con
            // nulo es la de la casa; hoy sería complejidad sin caso de uso.
            $table->unique('organizacion_id');
        });

        // Firma y fecha van juntas: una aprobación sin fecha no se puede fechar
        // después sin inventársela, y una fecha sin firma no aprueba nada.
        DB::statement('ALTER TABLE metodologias_riesgo ADD CONSTRAINT metodologias_riesgo_aprobacion_check CHECK ((aprobada_por_id IS NULL) = (aprobada_en IS NULL))');
        DB::statement('ALTER TABLE metodologias_riesgo ADD CONSTRAINT metodologias_riesgo_umbrales_check CHECK (umbral_aceptacion >= 2 AND umbral_critico >= umbral_aceptacion)');
        DB::statement('ALTER TABLE metodologias_riesgo ADD CONSTRAINT metodologias_riesgo_periodicidad_check CHECK (periodicidad_revision_meses BETWEEN 1 AND 60)');
        DB::statement('ALTER TABLE metodologias_riesgo ADD CONSTRAINT metodologias_riesgo_nombre_check CHECK (length(trim(nombre)) > 0)');

        Schema::create('riesgos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo')->comment('R-001, R-014');
            $table->string('titulo');

            /*
             * La amenaza es del catálogo global, o es texto libre. «El proveedor X
             * cierra» no está en MAGERIT y no tiene por qué estarlo; lo que no se
             * hace es una tabla de amenazas por tenant, que sería un catálogo
             * paralelo que nadie mantiene.
             *
             * `restrictOnDelete` y no `cascade`: el importador nunca borra una
             * amenaza —la marca como no vigente—, así que si alguien llega aquí
             * con un DELETE es que está haciendo algo que no debe, y es mejor que
             * la base se niegue que perder riesgos en cascada.
             */
            $table->foreignId('amenaza_id')->nullable()->constrained('amenazas')->restrictOnDelete();
            $table->string('amenaza_libre')->nullable();

            $table->text('vulnerabilidad')->nullable();

            // El «risk owner» de ISO 6.1.3 f): quien responde de la decisión. No
            // es quien hace el trabajo, que eso es el responsable de la tarea.
            $table->foreignId('propietario_id')->nullable()->constrained('users')->nullOnDelete();

            // Cuándo toca reevaluarlo. La columna entra ahora y el cableado al
            // calendario y al aviso diario va en la etapa siguiente: una fuente de
            // vencimientos que nadie mantiene mete una sección vacía en el correo.
            $table->date('fecha_revision')->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'fecha_revision']);
            $table->index(['organizacion_id', 'propietario_id']);
        });

        DB::statement('ALTER TABLE riesgos ADD CONSTRAINT riesgos_titulo_check CHECK (length(trim(titulo)) > 0)');

        // Exactamente una de las dos. Las dos a la vez es un riesgo que dice ser
        // dos cosas; ninguna es un riesgo sin amenaza, que no es un riesgo.
        DB::statement('ALTER TABLE riesgos ADD CONSTRAINT riesgos_amenaza_check CHECK ((amenaza_id IS NULL) <> (amenaza_libre IS NULL))');

        Schema::create('activo_riesgo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();

            $table->foreignId('vinculado_por_id')->nullable()->constrained('users')->nullOnDelete();

            // Sin `updated_at`: un vínculo se crea y se quita, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['riesgo_id', 'activo_id']);
            $table->index(['organizacion_id', 'activo_id']);
        });

        Schema::create('riesgo_implantacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            // Por qué ese control cubre este riesgo. Es lo que el auditor lee
            // cuando pregunta de dónde sale el residual.
            $table->text('nota')->nullable();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['riesgo_id', 'implantacion_id']);
            $table->index(['organizacion_id', 'implantacion_id']);
        });

        Schema::create('riesgo_valoraciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();

            $table->boolean('vigente')->default(true);

            $table->smallInteger('probabilidad');
            $table->smallInteger('impacto');

            // El impacto desglosado, que es lo que permite decir POR QUÉ vale lo
            // que vale: «5 en disponibilidad, 2 en confidencialidad».
            $table->jsonb('impacto_por_dimension')->default(DB::raw("'{}'::jsonb"));

            $table->smallInteger('riesgo_intrinseco');

            // Declarados, nunca calculados. Nulos mientras no se haya decidido
            // qué hacer con el riesgo.
            $table->smallInteger('probabilidad_residual')->nullable();
            $table->smallInteger('impacto_residual')->nullable();
            $table->smallInteger('riesgo_residual')->nullable();
            $table->text('justificacion_residual')->nullable();

            $table->string('decision')->default('mitigar');

            /*
             * Las dos instantáneas, y ninguna es redundante.
             *
             * `escala`: la metodología congelada. Sin ella, cambiar la escala en
             * enero convierte todas las valoraciones anteriores en cifras sin
             * unidades y «histórico comparable» deja de serlo.
             *
             * `salvaguardas`: las implantaciones tal como estaban al valorar. Sin
             * ella la fila MIENTE en cuanto una implantación cambie de estado el
             * mes que viene — es el mismo motivo por el que el .docx se construye
             * desde la instantánea y no desde una consulta nueva.
             */
            $table->jsonb('escala');
            $table->jsonb('salvaguardas')->default(DB::raw("'[]'::jsonb"));

            $table->foreignId('valorada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('valorada_en');

            $table->foreignId('aceptada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('aceptada_en')->nullable();

            /*
             * Dos notas y no una, porque las escriben dos personas en dos
             * momentos: `nota` es lo que dijo quien valoró y `nota_aceptacion` es
             * lo que dijo quien firmó —«aceptado en el comité del 3 de marzo»—.
             * Con una sola columna, firmar pisaría el razonamiento de la
             * valoración, que es justo lo que el auditor quiere leer al lado de la
             * firma.
             */
            $table->text('nota')->nullable();
            $table->text('nota_aceptacion')->nullable();

            // Sin `updated_at`: es histórico. Y con el trigger de la migración
            // siguiente, además es inmutable en cuanto se acepta o se jubila.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organizacion_id', 'riesgo_id', 'valorada_en']);
        });

        DB::statement("ALTER TABLE riesgo_valoraciones ADD CONSTRAINT riesgo_valoraciones_decision_check CHECK (decision IN ({$decisiones}))");
        DB::statement('ALTER TABLE riesgo_valoraciones ADD CONSTRAINT riesgo_valoraciones_escalones_check CHECK (probabilidad >= 1 AND impacto >= 1 AND riesgo_intrinseco >= 1)');
        DB::statement('ALTER TABLE riesgo_valoraciones ADD CONSTRAINT riesgo_valoraciones_aceptacion_check CHECK ((aceptada_por_id IS NULL) = (aceptada_en IS NULL))');

        // Una salvaguarda no empeora un riesgo. Un residual por encima del
        // intrínseco no es un juicio: es un error de captura.
        DB::statement('ALTER TABLE riesgo_valoraciones ADD CONSTRAINT riesgo_valoraciones_residual_check CHECK (riesgo_residual IS NULL OR riesgo_residual <= riesgo_intrinseco)');

        /*
         * Cuál es la vigente, por índice único parcial y no por una clave en
         * `riesgos` que apunte de vuelta. Es el mismo patrón que garantiza un solo
         * borrador por documento, y de paso es lo que permite que
         * `RiesgoRecurso::consulta()` una esta tabla sin multiplicar filas.
         */
        DB::statement('CREATE UNIQUE INDEX riesgo_valoraciones_vigente_unica ON riesgo_valoraciones (riesgo_id) WHERE vigente');
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_valoraciones');
        Schema::dropIfExists('riesgo_implantacion');
        Schema::dropIfExists('activo_riesgo');
        Schema::dropIfExists('riesgos');
        Schema::dropIfExists('metodologias_riesgo');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
    }
};
