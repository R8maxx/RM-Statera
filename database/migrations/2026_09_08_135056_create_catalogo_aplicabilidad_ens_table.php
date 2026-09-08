<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de aplicabilidad del Anexo II del ENS.
 *
 * Indica, para cada medida, qué se exige en cada categoría. Es la entrada del
 * motor de categorización: la aplicabilidad se DERIVA de aquí, nunca se marca
 * a mano (invariante 4 de la especificación).
 *
 * Doble lectura de la columna `categoria`, y el motor tiene que soportar las
 * dos: si `dimension_moduladora` es nula, el valor es la categoría del sistema;
 * si no lo es, el valor se lee como el nivel de esa dimensión concreta
 * (típicamente disponibilidad o trazabilidad), que es como se modulan algunas
 * medidas del marco operacional. La correspondencia básica/media/alta con
 * bajo/medio/alto es 1:1 en el ENS, de ahí que comparta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicabilidad_ens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
            $table->string('categoria');
            $table->string('exigencia')->comment('no_aplica, aplica, R1, R2...');
            $table->string('dimension_moduladora', 1)->nullable();
            $table->timestamps();

            $table->unique(['requisito_id', 'categoria']);
        });

        DB::statement("ALTER TABLE aplicabilidad_ens ADD CONSTRAINT aplicabilidad_ens_categoria_check CHECK (categoria IN ('basica', 'media', 'alta'))");
        DB::statement("ALTER TABLE aplicabilidad_ens ADD CONSTRAINT aplicabilidad_ens_dimension_check CHECK (dimension_moduladora IS NULL OR dimension_moduladora IN ('C', 'I', 'D', 'A', 'T'))");
        DB::statement("ALTER TABLE aplicabilidad_ens ADD CONSTRAINT aplicabilidad_ens_exigencia_check CHECK (exigencia = 'no_aplica' OR exigencia = 'aplica' OR exigencia ~ '^R[0-9]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicabilidad_ens');
    }
};
