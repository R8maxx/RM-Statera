<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles de cumplimiento del ENS (serie CCN-STIC 890, incluido el de
 * requisitos esenciales).
 *
 * Son una VISTA FILTRADA del catálogo, no un catálogo aparte: un perfil
 * selecciona un subconjunto de requisitos ya existentes y, opcionalmente,
 * ajusta la exigencia. El motor de categorización los aplica intersecando
 * con el resultado de la matriz por categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_cumplimiento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marco_id')->constrained('marcos')->cascadeOnDelete();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('referencia')->nullable()->comment('Guía CCN-STIC de la que sale');
            $table->timestamps();
        });

        Schema::create('perfil_requisitos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfiles_cumplimiento')->cascadeOnDelete();
            $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
            $table->string('exigencia')->nullable()->comment('Nula = la que dicte la categoría');
            $table->timestamps();

            $table->unique(['perfil_id', 'requisito_id']);
        });

        DB::statement("ALTER TABLE perfil_requisitos ADD CONSTRAINT perfil_requisitos_exigencia_check CHECK (exigencia IS NULL OR exigencia = 'no_aplica' OR exigencia = 'aplica' OR exigencia ~ '^R[0-9]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_requisitos');
        Schema::dropIfExists('perfiles_cumplimiento');
    }
};
