<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `no_conformidades.incidente_id`: el espejo exacto de `hallazgo_id`. § 4.10.
 *
 * Un incidente **puede** acabar en una no conformidad —el correo fraudulento que
 * funcionó porque el filtro estaba mal configurado— y entonces el tratamiento, la
 * causa raíz y la verificación de eficacia viven ahí y no aquí. Lo que este
 * módulo aporta es de dónde salió.
 *
 * Cuatro decisiones, todas copiadas del hallazgo y por los mismos motivos:
 *
 * 1. **Único**: un incidente se trata una vez. Sin esto, «incidentes sin tratar»
 *    dependería de cuál de las dos filas se mirase. En PostgreSQL los nulos son
 *    distintos entre sí, así que deja pasar todas las no conformidades sueltas
 *    que hagan falta — que son la mayoría.
 * 2. **`nullOnDelete` y no cascada**: borrar un incidente no puede llevarse por
 *    delante la prueba de que se trató.
 * 3. **`CHECK` de que implica `origen = 'incidente'`**, en una sola dirección:
 *    una de origen incidente sin fila detrás es legítima —la que se apunta de un
 *    incidente que no está registrado en Statera—, igual que pasa con las
 *    auditorías.
 * 4. **Y un `CHECK` que no tiene la de hallazgo**: no puede venir de un hallazgo
 *    y de un incidente a la vez. Con las dos columnas puestas, `origen` tendría
 *    que valer dos cosas, y los dos `CHECK` anteriores se contradirían con un
 *    mensaje que no explica nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_conformidades', function (Blueprint $table): void {
            $table->foreignId('incidente_id')->nullable()->after('hallazgo_id')
                ->constrained('incidentes')->nullOnDelete();

            $table->unique('incidente_id');
        });

        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_incidente_origen_check CHECK (incidente_id IS NULL OR origen = 'incidente')");
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_una_procedencia_check CHECK (hallazgo_id IS NULL OR incidente_id IS NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT IF EXISTS no_conformidades_una_procedencia_check');
        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT IF EXISTS no_conformidades_incidente_origen_check');

        Schema::table('no_conformidades', function (Blueprint $table): void {
            $table->dropUnique(['incidente_id']);
            $table->dropConstrainedForeignId('incidente_id');
        });
    }
};
