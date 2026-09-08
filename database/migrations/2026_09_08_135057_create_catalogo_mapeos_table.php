<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeos entre requisitos de marcos distintos.
 *
 * Es la pieza que justifica todo el proyecto: sin esta tabla, una evidencia que
 * sirve a un control ISO y a tres medidas ENS se mantiene por triplicado, que
 * es exactamente el problema que hoy se sufre en hojas de cálculo.
 *
 * `tipo_correspondencia` no es decorativo: `parcial` significa que el requisito
 * destino cubre solo una parte del origen, y la `nota` dice qué parte. Tratar
 * una correspondencia parcial como equivalente es la forma más rápida de
 * declarar implantado algo que no lo está.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapeos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisito_origen_id')->constrained('requisitos')->cascadeOnDelete();
            $table->foreignId('requisito_destino_id')->constrained('requisitos')->cascadeOnDelete();
            $table->string('tipo_correspondencia');
            $table->text('nota')->nullable()->comment('Qué cubre y qué no');
            $table->timestamps();

            $table->unique(['requisito_origen_id', 'requisito_destino_id']);
            $table->index('requisito_destino_id');
        });

        DB::statement("ALTER TABLE mapeos ADD CONSTRAINT mapeos_tipo_check CHECK (tipo_correspondencia IN ('equivalente', 'parcial', 'relacionado'))");
        DB::statement('ALTER TABLE mapeos ADD CONSTRAINT mapeos_no_reflexivo_check CHECK (requisito_origen_id <> requisito_destino_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mapeos');
    }
};
