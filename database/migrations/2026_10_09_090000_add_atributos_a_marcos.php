<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El vocabulario de los atributos de un marco: qué dimensiones hay, qué
 * valores admite cada una y cómo se llaman.
 *
 * Los requisitos de la ISO 27001 guardan sus cinco atributos de la ISO 27002
 * como slugs —`gestion_de_identidad_y_acceso`—, y un filtro no puede enseñar
 * eso. La etiqueta no va en un enum de PHP porque el catálogo es datos: viene
 * del YAML, como todo lo demás del marco.
 *
 * **Una lista, no un mapa.** JSONB no conserva el orden de las claves, y el
 * orden de los valores —preventivo, detectivo, correctivo; identificar antes
 * que recuperar— es el de la norma.
 *
 * Vacío para los marcos que no declaran atributos: el ENS no los tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcos', function (Blueprint $table): void {
            $table->jsonb('atributos')->default(DB::raw("'[]'::jsonb"))->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('marcos', function (Blueprint $table): void {
            $table->dropColumn('atributos');
        });
    }
};
