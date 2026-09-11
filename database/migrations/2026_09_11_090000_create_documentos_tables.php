<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se le entrega al auditor.
 *
 * Hasta aquí Statera sabe contestar qué aplica, cómo se cumple y dónde está la
 * prueba; estas dos tablas son las que además saben **enseñárselo a alguien**.
 * La Declaración de Aplicabilidad de ISO y la del ENS no se mantienen a mano:
 * son dos consultas sobre `implantaciones` congeladas en un PDF.
 *
 * **Por qué son dos tablas y no una.** La § 2.2 de la especificación describe
 * `documentos` con `version`, `estado` y `fichero` en la misma fila. Una fila no
 * sostiene un histórico, y la propia especificación pide versionado: `documentos`
 * es la serie —«la SoA del SGSI»— y `documento_versiones` es cada entrega con su
 * PDF, su huella y su fecha.
 *
 * **La regla que manda sobre el resto.** Un PDF emitido se almacena, no se
 * regenera. La SoA que se entregó en marzo tiene que poder enseñarse en octubre
 * tal cual se firmó: si se regenerase, el contenido habría cambiado y no habría
 * forma de demostrar qué se entregó. De ahí `numero`, `hash_sha256`,
 * `instantanea` y el trigger de la migración siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Una SoA es de un sistema, no de la organización: el alcance
            // declarado y la categoría salen de él. Nullable porque los tipos
            // que vendrán después —política general, acta de revisión— son de
            // ámbito organizativo y no cuelgan de ningún sistema.
            $table->foreignId('sistema_id')->nullable()->constrained('sistemas')->cascadeOnDelete();

            $table->string('codigo')->comment('SOA-SGSI-01, DDA-ENS-01');
            $table->string('titulo');
            $table->string('tipo');

            // Va impresa en el pie de cada página. No es adorno: estampar la
            // clasificación en el documento es `mp.info.2`.
            $table->string('clasificacion')->default('uso_interno');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'tipo']);
            $table->index(['organizacion_id', 'sistema_id']);
        });

        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_check CHECK (tipo IN ('soa_iso', 'dda_ens'))");
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_clasificacion_check CHECK (clasificacion IN ('publico', 'uso_interno', 'confidencial'))");

        // Las dos declaraciones se emiten para un sistema concreto. El CHECK se
        // escribe en negativo para que añadir un tipo de ámbito organizativo no
        // obligue a reescribirlo.
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_sistema_check CHECK (sistema_id IS NOT NULL OR tipo NOT IN ('soa_iso', 'dda_ens'))");

        Schema::create('documento_versiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();

            // NULL es el borrador, que se puede regenerar cuantas veces haga
            // falta. Un número es una versión emitida, y a partir de ahí la fila
            // no se toca nunca más.
            //
            // No hay `1.3` ni `2.0-rc1` a propósito: nadie sabe defender qué es
            // un «cambio menor» de una SoA, y el auditor cita «la SoA v4, de 12
            // de marzo de 2026».
            $table->unsignedInteger('numero')->nullable();

            // El ciclo de vida del TRABAJO que produce el PDF, no el flujo de
            // aprobación del documento. El nombre `estado` queda libre a
            // propósito para `borrador|en_revision|aprobado|obsoleto`, que es
            // otra cosa y llega con el § 4.5.
            $table->string('estado_generacion')->default('encolada');

            // El fichero, con el mismo reparto de columnas que `evidencias`: el
            // disco va con la fila porque una ruta sin su disco no localiza
            // nada, y el SHA-256 es lo que permite demostrar que el PDF que se
            // enseña es el que se generó aquel día.
            $table->string('disco')->nullable();
            $table->string('ruta')->nullable();
            $table->string('nombre_fichero')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->char('hash_sha256', 64)->nullable();

            // Los datos exactos que se pintaron. Un PDF no se puede consultar:
            // sin esto no hay forma de contestar «¿qué cambió entre la v3 y la
            // v4?» ni de demostrar que el PDF corresponde a lo que había en la
            // base ese día.
            $table->jsonb('instantanea')->default(DB::raw("'{}'::jsonb"));

            // Lo que se PIDIÓ, frente a lo que salió. Es entrada, no salida, y
            // por eso no vive dentro de la instantánea.
            $table->jsonb('parametros')->default(DB::raw("'{}'::jsonb"));

            // Denormalizados para que la tabla de versiones no tenga que abrir
            // un jsonb de doscientos kilobytes por fila sólo para decir «93
            // controles, 4 excluidos».
            $table->unsignedInteger('total_requisitos')->nullable();
            $table->unsignedInteger('total_excluidos')->nullable();
            $table->unsignedInteger('total_implantados')->nullable();

            // Por qué se emite esta versión. El auditor lo pregunta.
            $table->text('motivo')->nullable();

            // El mensaje del fallo, literal. Un fallo sin motivo no se
            // diagnostica.
            $table->text('error')->nullable();

            // En cola no hay sesión ni `Auth::id()`, así que la traza de
            // auditoría registrará la generación sin autor. Esta columna es la
            // única constancia de quién pulsó el botón.
            $table->foreignId('generada_por_id')->nullable()->constrained('users')->nullOnDelete();

            // `created_at` es cuándo se pidió; esto es cuándo se emitió.
            $table->timestampTz('emitida_en')->nullable();

            $table->timestamps();

            $table->unique(['documento_id', 'numero']);
            $table->index(['organizacion_id', 'documento_id']);
            $table->index(['documento_id', 'numero']);
            $table->index(['organizacion_id', 'estado_generacion']);
        });

        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_estado_check CHECK (estado_generacion IN ('encolada', 'generando', 'generada', 'fallida'))");

        // Un fichero sin su huella no se puede demostrar íntegro, que es la
        // única razón por la que se guarda una huella. Mismo criterio que
        // `evidencias_huella_check`, y además en las dos direcciones: una
        // versión «generada» sin fichero sería una descarga rota.
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_fichero_check CHECK ((estado_generacion = 'generada') = (ruta IS NOT NULL AND disco IS NOT NULL AND hash_sha256 IS NOT NULL))");

        // No se emite lo que no se ha generado.
        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_emision_check CHECK (numero IS NULL OR estado_generacion = 'generada')");

        DB::statement("ALTER TABLE documento_versiones ADD CONSTRAINT documento_versiones_error_check CHECK (estado_generacion <> 'fallida' OR error IS NOT NULL)");

        // Un solo borrador vivo por documento. Es lo que permite que «Generar»
        // sea idempotente sin acumular basura, y el índice parcial es la forma
        // de decirlo sin estorbar a las versiones emitidas, que sí son muchas.
        DB::statement('CREATE UNIQUE INDEX documento_versiones_borrador_unico ON documento_versiones (documento_id) WHERE numero IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versiones');
        Schema::dropIfExists('documentos');
    }
};
