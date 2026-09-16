<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\EstadoDocumental;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién firma lo que se entrega, y quién ha leído lo que se firmó.
 *
 * Es el § 4.5, y el hueco estaba reservado por escrito: `estado_generacion` se
 * llama así para dejar libre el nombre `estado`, que es el ciclo de vida del
 * DOCUMENTO y no el del trabajo que produce el PDF.
 *
 * **Aprobar es lo que emite.** El borrador pasa a `en_revision` y sigue siendo
 * regenerable; la firma de la dirección es el acto que asigna número, congela el
 * PDF y lo mueve a `emitidas/`. No es una preferencia de flujo: la portada se
 * congela en `instantanea` al generar y el trigger vuelve la fila inmutable en
 * cuanto tiene número, así que una firma posterior **no podría salir impresa en
 * el documento que se le entrega al auditor**, que es justamente donde la busca.
 *
 * **Cinco estados y no los cuatro de la § 2.2.** Falta uno para «la dirección lo
 * ha mirado y ha dicho que no», y sin él una versión tumbada se queda en
 * «pendiente de firma» para siempre y nadie sabe por qué. `rechazado` exige
 * motivo, igual que `descartada` en tareas: descartar es una decisión, y una
 * decisión sin motivo escrito no se puede auditar.
 *
 * **Los destinatarios del acuse son todos los usuarios de la organización**, y no
 * hay tabla de destinatarios: los pendientes salen de restar quien ya acusó. Es
 * lo más simple que no miente mientras el módulo de personas (§ 4.8) no exista,
 * y el documento lo declara como limitación en vez de disimularlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $estados = implode(', ', array_map(
            static fn (EstadoDocumental $estado): string => "'".$estado->value."'",
            EstadoDocumental::cases(),
        ));

        // --- La política de revisión, que es de la serie y no de la entrega ---

        Schema::table('documentos', function (Blueprint $table): void {
            /*
             * Cada cuánto toca revisar el documento. Nulo es «no lo revisamos por
             * calendario», que es una respuesta legítima para una Declaración de
             * Aplicabilidad —se rehace cuando cambia el alcance, no cuando pasa
             * un año— y una mala idea para una política.
             */
            $table->smallInteger('periodicidad_revision_meses')->nullable();

            // Apagado por defecto: nadie acusa recibo de una SoA.
            $table->boolean('exige_acuse')->default(false);
        });

        // El mismo rango que `metodologias_riesgo_periodicidad_check`, y por lo
        // mismo: por debajo de un mes no es una periodicidad y por encima de
        // cinco años no es una revisión.
        DB::statement('ALTER TABLE documentos ADD CONSTRAINT documentos_periodicidad_check CHECK (periodicidad_revision_meses IS NULL OR periodicidad_revision_meses BETWEEN 1 AND 60)');

        // --- El estado de cada entrega ---------------------------------------

        Schema::table('documento_versiones', function (Blueprint $table): void {
            $table->string('estado')->default(EstadoDocumental::Borrador->value);

            $table->foreignId('aprobada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('aprobada_en')->nullable();
            $table->text('nota_aprobacion')->nullable();

            $table->text('motivo_rechazo')->nullable();

            // Se calcula al firmar, desde la periodicidad del documento, y se
            // congela aquí. Vive en la versión y no en el documento porque la
            // pregunta es «cuándo caduca ESTA revisión», y la contesta la fecha
            // en que se firmó, no la de hoy.
            $table->date('fecha_proxima_revision')->nullable();

            $table->date('obsoleta_en')->nullable();

            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_proxima_revision']);
        });

        /*
         * Las versiones que ya estaban emitidas cuando no existía el flujo.
         *
         * Bajo el modelo nuevo una fila con número está aprobada, y éstas no lo
         * están: se entregaron antes de que hubiera nadie a quien pedirle la
         * firma. Rellenarles `aprobada_por_id` con quien pulsó «Generar» sería
         * **fabricar una firma**, que es exactamente lo que estas tablas existen
         * para hacer imposible.
         *
         * Se archivan como obsoletas, que es lo único cierto que se puede decir
         * de ellas: se entregaron y ya no son la vigente. `obsoleta_en` se queda
         * a nulo porque tampoco se sabe cuándo dejaron de serlo, y por eso el
         * CHECK de más abajo no la exige.
         */
        DB::table('documento_versiones')
            ->whereNotNull('numero')
            ->update(['estado' => EstadoDocumental::Obsoleto->value]);

        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_estado_documental_check CHECK (estado IN ({$estados}))");

        // Con número se está aprobado u obsoleto, y sin número no. Es la misma
        // frontera que ya marcaba `numero IS NULL`, dicha ahora en el vocabulario
        // del documento en vez de en el de la fila.
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_numero_estado_check CHECK ((numero IS NULL) = (estado IN ('borrador', 'en_revision', 'rechazado')))");

        /*
         * Lo que está aprobado lleva firma y fecha. En una sola dirección a
         * propósito: la firma se escribe en la fila ANTES de regenerar y numerar
         * —el PDF tiene que salir con ella impresa—, así que una fila
         * `en_revision` con aprobación puesta es un estado legítimo y
         * transitorio. Una implicación en las dos direcciones haría fallar la
         * generación justo en el paso que importa.
         *
         * `obsoleto` queda fuera por las versiones heredadas: archivarlas no
         * puede obligar a inventarles un firmante.
         */
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_firma_check CHECK (estado <> 'aprobado' OR (aprobada_en IS NOT NULL AND aprobada_por_id IS NOT NULL))");

        // Rechazar exige motivo, y el motivo no cuelga de ningún otro estado.
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_rechazo_check CHECK ((estado = 'rechazado') = (motivo_rechazo IS NOT NULL))");

        // Sólo una versión obsoleta puede llevar fecha de obsolescencia; no toda
        // obsoleta la lleva.
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_obsolescencia_check CHECK (obsoleta_en IS NULL OR estado = 'obsoleto')");

        /*
         * Una sola versión aprobada viva por documento. Es el mismo mecanismo que
         * `documento_versiones_borrador_unico` y que el de `riesgo_valoraciones`:
         * un índice único parcial es la forma de decir «de esto hay uno» sin
         * estorbar a las muchas filas que no lo son.
         */
        DB::statement("CREATE UNIQUE INDEX documento_versiones_aprobada_vigente ON documento_versiones (documento_id) WHERE estado = 'aprobado'");

        // --- El acuse de lectura ---------------------------------------------

        Schema::create('documento_lecturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            /*
             * Se acusa recibo de una VERSIÓN, no de un documento. Quien leyó la
             * v3 no ha leído la v4, y dar por buena la lectura de la anterior es
             * exactamente el fallo que la cláusula 7.3 existe para evitar: la
             * gente tiene que conocer la política vigente, no una que lo fue.
             */
            $table->foreignId('documento_version_id')->constrained('documento_versiones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestampTz('acusada_en');

            $table->timestamp('created_at')->nullable();

            $table->unique(['documento_version_id', 'user_id']);
            $table->index(['organizacion_id', 'documento_version_id']);
            $table->index(['organizacion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_lecturas');

        DB::statement('DROP INDEX IF EXISTS documento_versiones_aprobada_vigente');

        foreach ([
            'documento_versiones_estado_documental_check',
            'documento_versiones_numero_estado_check',
            'documento_versiones_firma_check',
            'documento_versiones_rechazo_check',
            'documento_versiones_obsolescencia_check',
        ] as $restriccion) {
            DB::statement("ALTER TABLE documento_versiones DROP CONSTRAINT IF EXISTS {$restriccion}");
        }

        Schema::table('documento_versiones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('aprobada_por_id');
            $table->dropColumn([
                'estado',
                'aprobada_en',
                'nota_aprobacion',
                'motivo_rechazo',
                'fecha_proxima_revision',
                'obsoleta_en',
            ]);
        });

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT IF EXISTS documentos_periodicidad_check');

        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropColumn(['periodicidad_revision_meses', 'exige_acuse']);
        });
    }
};
