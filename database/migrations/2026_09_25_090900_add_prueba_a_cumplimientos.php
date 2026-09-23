<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `compromiso_cumplimientos.prueba_continuidad_id`: una prueba de continuidad
 * realizada, citada como la referencia de un cumplimiento. § 4.11.
 *
 * Cuarta columna excluyente, mismo mecanismo que ya tienen `auditoria_id`,
 * `revision_direccion_id` y `documento_id`: FK explícita y no `morphTo` —el
 * motivo de siempre es el mismo, un nombre de clase PHP metido en la base—, y
 * `nullOnDelete` porque borrar la prueba no puede llevarse por delante la
 * prueba, en el sentido documental, de que la obligación se cumplió.
 *
 * `compromiso_cumplimientos_referencia_check` se sustituye entero en vez de
 * ampliarse porque ya usaba `num_nonnulls(...) <= 1`: la forma que escala a
 * una cuarta columna sin reescribir la comparación dos a dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compromiso_cumplimientos', function (Blueprint $table): void {
            $table->foreignId('prueba_continuidad_id')->nullable()->after('documento_id')
                ->constrained('pruebas_continuidad')->nullOnDelete();
        });

        DB::statement('ALTER TABLE compromiso_cumplimientos DROP CONSTRAINT compromiso_cumplimientos_referencia_check');
        DB::statement('ALTER TABLE compromiso_cumplimientos ADD CONSTRAINT compromiso_cumplimientos_referencia_check CHECK (num_nonnulls(auditoria_id, revision_direccion_id, documento_id, prueba_continuidad_id) <= 1)');
    }

    public function down(): void
    {
        /*
         * Vaciada **antes** de estrechar el `CHECK` de vuelta a tres columnas: si
         * quedara alguna fila con `prueba_continuidad_id` puesto, el
         * `ADD CONSTRAINT` siguiente la validaría contra los datos existentes y
         * moriría con «is violated by some row».
         *
         * Y por `comoMantenimiento()`: esta tabla SÍ lleva `organizacion_id` y
         * RLS, y una migración no tiene petición ni usuario — sin contexto, el
         * `update` se quedaría en cero filas sin fallar, el mismo fallo
         * silencioso que ya corrigió `2026_09_20_130300_…con_incidente.php`.
         */
        app(ContextoOrganizacion::class)->comoMantenimiento(static function (): void {
            DB::table('compromiso_cumplimientos')->whereNotNull('prueba_continuidad_id')->update(['prueba_continuidad_id' => null]);
        });

        DB::statement('ALTER TABLE compromiso_cumplimientos DROP CONSTRAINT compromiso_cumplimientos_referencia_check');
        DB::statement('ALTER TABLE compromiso_cumplimientos ADD CONSTRAINT compromiso_cumplimientos_referencia_check CHECK (num_nonnulls(auditoria_id, revision_direccion_id, documento_id) <= 1)');

        Schema::table('compromiso_cumplimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prueba_continuidad_id');
        });
    }
};
