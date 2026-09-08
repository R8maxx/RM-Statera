<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El centro del modelo: une un requisito del catálogo global con una organización.
 *
 * Contesta las tres preguntas de cualquier auditoría — qué aplica, cómo se
 * cumple y dónde está la prueba — y la Declaración de Aplicabilidad de ISO y la
 * del ENS son dos consultas distintas sobre esta misma tabla, nunca documentos
 * mantenidos a mano.
 *
 * El histórico va aparte, en `implantacion_transiciones`: el auditor no pregunta
 * "¿está implantado?", pregunta "¿desde cuándo?" (invariante 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implantaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();

            // Al catálogo global. Un requisito retirado de una revisión del marco
            // no se borra, así que esta clave nunca queda colgando.
            $table->foreignId('requisito_id')->constrained('requisitos')->restrictOnDelete();

            $table->boolean('aplica')->default(true);
            $table->text('justificacion')->nullable();

            // Qué nivel se exige según la categoría del sistema: `aplica`, `R1`,
            // `R2`… Lo calcula el motor; no se escribe a mano.
            $table->string('exigencia_calculada')->nullable();

            // Por qué se exige. Sin esto no hay forma de contestarle al auditor
            // de dónde sale la exigencia de una medida sin releer la matriz.
            $table->string('origen_exigencia')->nullable();
            $table->string('dimension_moduladora', 1)->nullable();

            $table->string('estado')->default('no_iniciado');

            // Escala L0–L5 del CCN, la que pide el informe INES.
            $table->string('nivel_madurez')->nullable();

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_objetivo')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['sistema_id', 'requisito_id']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['sistema_id', 'aplica']);
        });

        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_estado_check CHECK (estado IN ('no_iniciado', 'planificado', 'en_progreso', 'implantado', 'no_aplica'))");
        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_madurez_check CHECK (nivel_madurez IS NULL OR nivel_madurez IN ('l0', 'l1', 'l2', 'l3', 'l4', 'l5'))");
        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_exigencia_check CHECK (exigencia_calculada IS NULL OR exigencia_calculada = 'aplica' OR exigencia_calculada ~ '^R[0-9]+$')");
        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_origen_check CHECK (origen_exigencia IS NULL OR origen_exigencia IN ('categoria', 'modulacion_dimension', 'perfil', 'catalogo'))");
        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_dimension_check CHECK (dimension_moduladora IS NULL OR dimension_moduladora IN ('C', 'I', 'D', 'A', 'T'))");

        // Excluir un requisito sin decir por qué es exactamente lo que el auditor
        // rechaza. Lo dice la base, no sólo el FormRequest.
        DB::statement('ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_exclusion_justificada_check CHECK (aplica OR (justificacion IS NOT NULL AND length(trim(justificacion)) > 0))');

        // Coherencia entre las dos formas de decir que algo no se exige.
        DB::statement("ALTER TABLE implantaciones ADD CONSTRAINT implantaciones_no_aplica_coherente_check CHECK ((estado = 'no_aplica') = (NOT aplica))");

        Schema::create('implantacion_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            // Nulo en el alta: no venía de ningún estado.
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            // Nulo cuando la transición la provoca el sistema al recalcular, no
            // una persona.
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            // Sin `updated_at`: es histórico, no se edita.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['implantacion_id', 'created_at']);
        });

        DB::statement("ALTER TABLE implantacion_transiciones ADD CONSTRAINT transiciones_estado_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ('no_iniciado', 'planificado', 'en_progreso', 'implantado', 'no_aplica'))");
        DB::statement("ALTER TABLE implantacion_transiciones ADD CONSTRAINT transiciones_estado_nuevo_check CHECK (estado_nuevo IN ('no_iniciado', 'planificado', 'en_progreso', 'implantado', 'no_aplica'))");
        DB::statement('ALTER TABLE implantacion_transiciones ADD CONSTRAINT transiciones_no_reflexiva_check CHECK (estado_anterior IS DISTINCT FROM estado_nuevo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('implantacion_transiciones');
        Schema::dropIfExists('implantaciones');
    }
};
