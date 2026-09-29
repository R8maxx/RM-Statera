<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué dato de la ficha del proveedor contrasta cada cláusula.
 *
 * CLA-06 pregunta dónde están los datos y CLA-10 si hay encargo de tratamiento,
 * y la ficha del proveedor dice las dos cosas por su cuenta. Cuando la evaluación
 * y la ficha no casan, una de las dos está mal, y la ficha lo avisa.
 *
 * - **Va en el catálogo y no en el código** (invariante 3): qué cláusula mira qué
 *   dato lo dice el YAML. Lo que es código es la lista de datos de la ficha que
 *   se pueden contrastar, porque son columnas de `proveedores`.
 * - **Tabla global**, sin `organizacion_id` ni RLS, como el resto del catálogo.
 * - **El `CHECK` de valores va escrito a mano**, por la regla de
 *   `.ai/rules/migraciones.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clausulas_contractuales', function (Blueprint $table): void {
            $table->string('dato_de_ficha', 40)->nullable();
        });

        DB::statement("ALTER TABLE clausulas_contractuales ADD CONSTRAINT clausulas_contractuales_dato_de_ficha_check CHECK (dato_de_ficha IN ('ubicacion_datos', 'encargado_tratamiento'))");
    }

    public function down(): void
    {
        Schema::table('clausulas_contractuales', function (Blueprint $table): void {
            $table->dropColumn('dato_de_ficha');
        });
    }
};
