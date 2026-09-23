<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `obligaciones.requisito_id`: la obligación derivada de un requisito, y no de
 * una categoría copiada a mano. § 4.11.
 *
 * Las pruebas de continuidad no son exigibles a partir de una categoría del
 * sistema: `op.cont.3` sólo aplica cuando la dimensión de Disponibilidad llega
 * a alto, y eso ya lo decide el motor de categorización sobre `implantaciones`.
 * Duplicarlo como `categoria_minima: media` en el catálogo de obligaciones era
 * exigir de más —a sistemas media y alta cuya Disponibilidad no llega a alto—,
 * y copiar la regla es justo lo que el invariante 4 prohíbe: la aplicabilidad
 * se deriva, no se copia.
 *
 * **Global contra global, sin `organizacion_id` en ninguno de los dos lados**:
 * `obligaciones` y `requisitos` son las dos catálogo (invariante 2), así que
 * la FK no cruza el aislamiento multi-tenant y no hace falta RLS ni
 * `comoMantenimiento()` para tocarla.
 *
 * `nullOnDelete` y no cascada: la mayoría de las obligaciones no dependen de
 * ningún requisito —la revisión por la dirección no tiene uno—, y sigue siendo
 * cierto para las que sí lo tienen si el requisito llegara a borrarse, cosa
 * que hoy no ocurre porque el importador marca y no borra.
 *
 * Y se aprovecha para ampliar `obligaciones_referencia_check` con
 * `prueba_continuidad`: es la referencia que sugiere esta misma obligación.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const REFERENCIAS_ANTERIORES = ['auditoria', 'revision_direccion', 'documento'];

    /** @var list<string> */
    private const REFERENCIAS_ACTUALES = ['auditoria', 'revision_direccion', 'documento', 'prueba_continuidad'];

    public function up(): void
    {
        Schema::table('obligaciones', function (Blueprint $table): void {
            $table->foreignId('requisito_id')->nullable()->after('marco_id')
                ->constrained('requisitos')->nullOnDelete();
        });

        DB::statement('ALTER TABLE obligaciones DROP CONSTRAINT obligaciones_referencia_check');
        DB::statement("ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_referencia_check CHECK (referencia_sugerida IS NULL OR referencia_sugerida IN ({$this->lista(self::REFERENCIAS_ACTUALES)}))");
    }

    public function down(): void
    {
        // El catálogo es global y sin RLS: no hace falta `comoMantenimiento()`
        // para tocar todas las filas, a diferencia de `compromiso_cumplimientos`.
        DB::table('obligaciones')->where('referencia_sugerida', 'prueba_continuidad')->update(['referencia_sugerida' => null]);

        DB::statement('ALTER TABLE obligaciones DROP CONSTRAINT obligaciones_referencia_check');
        DB::statement("ALTER TABLE obligaciones ADD CONSTRAINT obligaciones_referencia_check CHECK (referencia_sugerida IS NULL OR referencia_sugerida IN ({$this->lista(self::REFERENCIAS_ANTERIORES)}))");

        Schema::table('obligaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requisito_id');
        });
    }

    /** @param list<string> $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
    }
};
