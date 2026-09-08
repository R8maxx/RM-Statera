<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo normativo: marcos, requisitos y refuerzos.
 *
 * Estas tablas son GLOBALES y compartidas entre organizaciones: no llevan
 * `organizacion_id` y nunca deben llevarlo (invariante 2 de la especificación).
 * El catálogo va como datos, no como código: nada de enums con los controles
 * dentro, porque ISO 27001 ya tiene una enmienda de 2024 y el ENS tendrá
 * revisiones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique()->comment('ISO27001-2022, ENS-RD311-2022');
            $table->string('nombre');
            $table->string('version');
            $table->date('fecha_vigencia')->nullable();
            $table->string('estado')->default('vigente');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE marcos ADD CONSTRAINT marcos_estado_check CHECK (estado IN ('vigente', 'derogado'))");

        Schema::create('requisitos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marco_id')->constrained('marcos')->cascadeOnDelete();
            $table->string('codigo')->comment('6.1.2, A.5.15, op.acc.4, mp.eq.2');
            $table->string('tipo');
            $table->foreignId('parent_id')->nullable()->constrained('requisitos')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);

            // Los cinco atributos que la ISO 27002:2022 asigna a cada control
            // van aquí, no en tabla propia: se consultan para filtrar y agrupar
            // en la interfaz, y JSONB con índice GIN da eso sin siete joins.
            $table->jsonb('atributos')->default(DB::raw("'{}'::jsonb"));

            // Un requisito que desaparece de una revisión del marco NO se borra:
            // se marca. Puede haber implantaciones colgando de él y el auditor
            // preguntará por ellas.
            $table->boolean('vigente')->default(true);
            $table->timestamp('retirado_en')->nullable();

            // Huella del contenido importado desde YAML. El importador la usa
            // para distinguir un requisito modificado de uno intacto sin
            // comparar campo a campo.
            $table->string('huella', 64)->nullable();

            $table->timestamps();

            $table->unique(['marco_id', 'codigo']);
            $table->index(['marco_id', 'parent_id']);
            $table->index(['marco_id', 'tipo']);
        });

        DB::statement("ALTER TABLE requisitos ADD CONSTRAINT requisitos_tipo_check CHECK (tipo IN ('clausula', 'control', 'medida'))");
        DB::statement('CREATE INDEX requisitos_atributos_gin ON requisitos USING GIN (atributos)');

        Schema::create('refuerzos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
            $table->string('codigo')->comment('R1, R2, R3...');
            $table->text('descripcion');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['requisito_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refuerzos');
        Schema::dropIfExists('requisitos');
        Schema::dropIfExists('marcos');
    }
};
