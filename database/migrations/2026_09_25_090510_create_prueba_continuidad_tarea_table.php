<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El trabajo correctivo que deja una prueba de continuidad. § 4.11.
 *
 * N:M, y la pivote lleva `organizacion_id` como `no_conformidad_tarea` y
 * `mejora_tarea`. No es redundante con las dos claves: es lo que la mete dentro
 * de las tres capas de aislamiento. Y ojo —**si se olvidara, `RlsDeclaradaTest`
 * no diría nada**, porque sólo mira las tablas que ya tienen la columna.
 *
 * **A diferencia de `no_conformidad_tarea` y `mejora_tarea`, esta pivote no nace
 * en la misma migración que su tabla dueña**: `pruebas_continuidad` ya existe
 * desde el § 4.11 del módulo anterior, y esto es una costura que se añade
 * encima, no parte del alta original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prueba_continuidad_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('prueba_continuidad_id')->constrained('pruebas_continuidad')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['prueba_continuidad_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prueba_continuidad_tarea');
    }
};
