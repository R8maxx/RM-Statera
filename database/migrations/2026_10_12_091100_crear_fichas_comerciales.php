<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ficha comercial de cada cliente (punto 54): con quién se habla de
 * facturación y lo que la plataforma anota para sí.
 *
 * **En su propia tabla y no en `organizaciones`.** Cada cambio de aquélla va a
 * la traza del cliente (`Organizacion::booted()`), y las notas comerciales son
 * internas: acabarían en su traza y en su exportación. Ésta es de la
 * plataforma: sin RLS y con `organizacion_afectada_id`, como el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_comerciales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_afectada_id')->unique()->constrained('organizaciones')->cascadeOnDelete();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_email')->nullable();
            $table->string('contacto_telefono')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('actualizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_comerciales');
    }
};
