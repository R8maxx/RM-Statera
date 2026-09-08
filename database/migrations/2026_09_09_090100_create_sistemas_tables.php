<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sistemas y su valoración en las cinco dimensiones.
 *
 * Un sistema es la unidad de alcance y de certificación: un SGSI de ISO o un
 * sistema del ENS. Lleva `marco_id` al catálogo GLOBAL, que no tiene
 * `organizacion_id` y nunca debe tenerlo.
 *
 * La CATEGORÍA no se almacena. Se deriva de las cinco filas de
 * `valoracion_dimensiones` con ValoracionDimensiones::categoria(). Guardar una
 * copia denormalizada es abrir la puerta a que se desincronice de la valoración,
 * y la aplicabilidad se deriva, no se selecciona (invariante 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistemas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('marco_id')->constrained('marcos')->restrictOnDelete();
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('borrador');

            // El alcance declarado y sus exclusiones son la cláusula 4.3 de ISO y
            // lo primero que lee un auditor.
            $table->text('alcance_declarado')->nullable();
            $table->text('exclusiones_justificadas')->nullable();

            // Perfil de cumplimiento del ENS (serie CCN-STIC 890), si se acoge a
            // alguno. Nulo = sólo la matriz por categoría.
            $table->foreignId('perfil_id')->nullable()->constrained('perfiles_cumplimiento')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'marco_id']);
        });

        DB::statement("ALTER TABLE sistemas ADD CONSTRAINT sistemas_estado_check CHECK (estado IN ('borrador', 'activo', 'archivado'))");

        Schema::create('valoracion_dimensiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();
            $table->string('dimension', 1);
            $table->string('nivel');

            // El ENS exige motivar la valoración: es lo que el auditor contrasta
            // cuando discute la categoría del sistema.
            $table->text('justificacion')->nullable();

            $table->timestamps();

            $table->unique(['sistema_id', 'dimension']);
        });

        DB::statement("ALTER TABLE valoracion_dimensiones ADD CONSTRAINT valoracion_dimension_check CHECK (dimension IN ('C', 'I', 'D', 'A', 'T'))");
        DB::statement("ALTER TABLE valoracion_dimensiones ADD CONSTRAINT valoracion_nivel_check CHECK (nivel IN ('na', 'bajo', 'medio', 'alto'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('valoracion_dimensiones');
        Schema::dropIfExists('sistemas');
    }
};
