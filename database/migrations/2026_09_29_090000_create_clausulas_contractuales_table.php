<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de cláusulas de seguridad con proveedores (§ 4.9).
 *
 * **Catálogo global, sin `organizacion_id`** (invariante 2), como las amenazas de
 * MAGERIT: lo que se comprueba en un contrato es el mismo vocabulario para todos
 * los clientes, y qué se comprobó en cada evaluación es de cada uno.
 *
 * Mismo contrato que el resto del catálogo: huella del contenido importado y
 * **retirada por marca, nunca por borrado**, porque hay evaluaciones que la
 * comprobaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clausulas_contractuales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            // Por clave natural `{marco, requisito}`: los ids son de la base.
            $table->jsonb('referencias')->default(DB::raw("'[]'::jsonb"));

            $table->unsignedInteger('orden')->default(0);
            $table->string('huella', 64)->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamp('retirado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clausulas_contractuales');
    }
};
