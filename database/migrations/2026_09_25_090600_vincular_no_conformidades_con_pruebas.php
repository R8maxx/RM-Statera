<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `no_conformidades.prueba_continuidad_id`: el espejo exacto de `incidente_id`.
 * § 4.11.
 *
 * Una prueba de continuidad que sale parcial o fallida puede destapar que la
 * organización no cumplía lo que su propio plan prometía —`op.cont.3`—, y eso se
 * trata aquí igual que cualquier otro incumplimiento: con causa raíz, plazo y
 * verificación de eficacia.
 *
 * Cuatro decisiones, las cuatro copiadas de `incidente_id` y por los mismos
 * motivos:
 *
 * 1. **Único**: una prueba se trata una vez. Sin esto, «pruebas sin tratar»
 *    dependería de cuál de las dos filas se mirase. En PostgreSQL los nulos son
 *    distintos entre sí, así que deja pasar todas las no conformidades sueltas
 *    que hagan falta — que son la mayoría.
 * 2. **`nullOnDelete` y no cascada**: borrar una prueba no puede llevarse por
 *    delante la prueba —en el sentido documental— de que se trató.
 * 3. **`CHECK` de que implica `origen = 'prueba_continuidad'`**, en una sola
 *    dirección: una no conformidad de ese origen sin fila detrás es legítima
 *    —la que se apunta a mano de una prueba que no está registrada en Statera—,
 *    igual que pasa con auditorías e incidentes.
 * 4. **Ampliar `no_conformidades_una_procedencia_check` a tres columnas.** Con
 *    `hallazgo_id`, `incidente_id` y ahora `prueba_continuidad_id`, «como mucho
 *    una procedencia» ya no se escribe con `a IS NULL OR b IS NULL`: hacen falta
 *    tres comparaciones dos a dos, o `num_nonnulls(...) <= 1`, que es la forma
 *    que PostgreSQL da para exactamente este caso y la que escala si algún día
 *    hay una cuarta. El nombre de la restricción se mantiene: sigue diciendo lo
 *    mismo, sólo que ahora sobre tres columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_conformidades', function (Blueprint $table): void {
            $table->foreignId('prueba_continuidad_id')->nullable()->after('incidente_id')
                ->constrained('pruebas_continuidad')->nullOnDelete();

            $table->unique('prueba_continuidad_id');
        });

        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT no_conformidades_origen_check');
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_origen_check CHECK (origen IN ('auditoria', 'incidente', 'revision_direccion', 'prueba_continuidad', 'propia'))");

        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_prueba_continuidad_origen_check CHECK (prueba_continuidad_id IS NULL OR origen = 'prueba_continuidad')");

        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT no_conformidades_una_procedencia_check');
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_una_procedencia_check CHECK (num_nonnulls(hallazgo_id, incidente_id, prueba_continuidad_id) <= 1)');
    }

    public function down(): void
    {
        /*
         * Reasignadas **antes** de estrechar `no_conformidades_origen_check` de
         * vuelta a cuatro valores: si quedara alguna fila con
         * `origen = 'prueba_continuidad'`, el `ADD CONSTRAINT` siguiente la
         * validaría contra los datos existentes y moriría con «is violated by
         * some row». Y por mantenimiento: una migración no tiene petición ni
         * usuario, así que sin contexto RLS dejaría el `update` en cero filas
         * **sin fallar**, que es el mismo fallo silencioso que ya corrigió
         * `2026_09_20_130300_…con_incidente.php`.
         */
        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::table('no_conformidades')->where('origen', 'prueba_continuidad')->update(['origen' => 'propia']);
        });

        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT no_conformidades_una_procedencia_check');
        DB::statement('ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_una_procedencia_check CHECK (hallazgo_id IS NULL OR incidente_id IS NULL)');

        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT IF EXISTS no_conformidades_prueba_continuidad_origen_check');

        DB::statement('ALTER TABLE no_conformidades DROP CONSTRAINT no_conformidades_origen_check');
        DB::statement("ALTER TABLE no_conformidades ADD CONSTRAINT no_conformidades_origen_check CHECK (origen IN ('auditoria', 'incidente', 'revision_direccion', 'propia'))");

        Schema::table('no_conformidades', function (Blueprint $table): void {
            $table->dropUnique(['prueba_continuidad_id']);
            $table->dropConstrainedForeignId('prueba_continuidad_id');
        });
    }
};
