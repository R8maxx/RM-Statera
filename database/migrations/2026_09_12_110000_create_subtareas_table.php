<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La lista de comprobación de una tarea.
 *
 * **Una subtarea no es una tarea, y por eso no está en `tareas` con un
 * `parent_id`.** Es un paso de una lista: título, marcada o no, y cuándo. Sin
 * responsable, sin plazo, sin prioridad, sin estado y sin histórico.
 *
 * El motivo es aritmético y es el que decide el diseño: **hoy hay trece sitios
 * que cuentan tareas** —el panel, los indicadores de la tabla, el reparto por
 * estado y por prioridad, las columnas del tablero, el calendario y el aviso
 * diario—. Con las subtareas como filas de `tareas`, cada uno de esos trece
 * tendría que decidir si suma la madre, las hijas o las dos, y el día que uno se
 * despiste el panel dirá doce tareas abiertas donde hay cuatro cosas que hacer.
 * Contar de más es el fallo caro, y aquí se evita no dando la ocasión.
 *
 * Lo que se pierde es poder asignar un paso a alguien o ponerle fecha propia.
 * Cuando eso haga falta, lo que hace falta es una tarea de pleno derecho
 * vinculada al mismo requisito, no una subtarea con más campos.
 *
 * `hecha_en` y no un booleano: «cuándo se marcó» es la primera pregunta que hace
 * un auditor y cuesta exactamente lo mismo guardarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtareas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();

            $table->string('titulo');
            $table->timestampTz('hecha_en')->nullable();

            // El orden lo decide quien redacta la lista: los pasos de un
            // procedimiento no se ordenan solos por fecha ni por título.
            $table->unsignedInteger('orden')->default(0);

            $table->timestamps();

            $table->index(['tarea_id', 'orden']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        // Un paso en blanco deja una casilla que nadie sabe qué marca.
        DB::statement('ALTER TABLE subtareas ADD CONSTRAINT subtareas_titulo_check CHECK (length(trim(titulo)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subtareas');
    }
};
