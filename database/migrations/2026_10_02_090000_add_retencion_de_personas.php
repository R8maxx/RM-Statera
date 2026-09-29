<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La retención de los datos de personas (§ 6, punto 36).
 *
 * «Política de retención y borrado alineada con RGPD para los datos de
 * personas». El RGPD no pone un número —pide conservar «no más tiempo del
 * necesario» (art. 5.1.e)—, así que el plazo es **política de la
 * organización**, como la reevaluación de proveedores o el plazo de una
 * vulnerabilidad. Y a diferencia de ésas, **no lleva valor por defecto**:
 * mientras nadie lo declare, `personas:seudonimizar` no toca a nadie. Inventar
 * un plazo sería la herramienta decidiendo cuándo se borra el expediente de
 * alguien.
 *
 * `personas.seudonimizada_en` marca a quien ya pasó por ahí. No es un estado
 * que se pueda deshacer: los datos ya no están.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->unsignedSmallInteger('retencion_personas_meses')->nullable();
        });

        DB::statement('ALTER TABLE organizaciones ADD CONSTRAINT organizaciones_retencion_personas_check CHECK (retencion_personas_meses BETWEEN 1 AND 600)');

        Schema::table('personas', function (Blueprint $table): void {
            $table->timestamp('seudonimizada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn('seudonimizada_en');
        });

        DB::statement('ALTER TABLE organizaciones DROP CONSTRAINT IF EXISTS organizaciones_retencion_personas_check');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn('retencion_personas_meses');
        });
    }
};
