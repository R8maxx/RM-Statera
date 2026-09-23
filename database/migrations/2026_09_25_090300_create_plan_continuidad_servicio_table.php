<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué servicios cubre un plan de continuidad: § 4.11.
 *
 * N:M entre `documentos` (de tipo `plan_continuidad`) y `activos` (de tipo
 * `servicios`). Ninguna de las dos condiciones la impone un `CHECK` de esta
 * tabla —comprobar una columna de otra tabla desde una restricción no es
 * portable—, así que las dos viven en el dominio: `VincularServicioAPlan`
 * exige el tipo de documento y el tipo de activo antes de escribir nada, igual
 * que `RegistrarBia` con el BIA.
 *
 * **`organizacion_id` como cualquier otra tabla de datos propios** (invariante
 * 1), aunque las dos filas que enlaza ya la llevan: sin ella, RLS no tiene
 * columna que comparar y rechaza la inserción con un error que no menciona la
 * palabra «organización».
 *
 * El único índice único es `(documento_id, activo_id)`: un plan puede cubrir
 * varios servicios y un servicio puede estar cubierto por más de un plan —dos
 * unidades de negocio con planes separados sobre el mismo servicio compartido
 * es un caso real—, pero el mismo par no se repite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_continuidad_servicio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['documento_id', 'activo_id']);
            $table->index(['organizacion_id', 'activo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_continuidad_servicio');
    }
};
