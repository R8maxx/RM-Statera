<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo y quién impartió una sesión: modalidad e impartición.
 *
 * - **`modalidad`**: presencial, en línea o mixta. Nula en las sesiones que ya
 *   había, porque nadie lo apuntó y rellenarlo sería inventarlo.
 * - **`imparte`**: interna o externa, y de ahí cuelga quién.
 *   - **Interna → `ponente_persona_id`**, alguien de la plantilla. Persona y no
 *     `users`: quien da una charla de concienciación no tiene por qué tener
 *     cuenta en Statera, que es la misma frontera de `personas.md`.
 *   - **Externa → `ponente_nombre`** y, si se conoce, **`proveedor_id`**: la
 *     academia que la imparte es un tercero que ya puede estar dado de alta en
 *     proveedores, con su contrato evaluado. El nombre va en texto porque el
 *     formador de un proveedor no es de la plantilla.
 * - **Los `CHECK` impiden mezclar las dos**: una sesión interna con proveedor, o
 *   externa con ponente de la plantilla, es un dato que se contradice solo. No
 *   exigen el ponente: borrar a la persona o al proveedor pone la clave a nulo, y
 *   la sesión sigue siendo interna o externa. Lo exige el `FormRequest` al
 *   guardar.
 * - Los valores de los `CHECK` van escritos a mano, por la regla de
 *   `.ai/rules/migraciones.md`.
 *
 * La RLS de `acciones_formativas` ya cubre las columnas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_formativas', function (Blueprint $table): void {
            $table->string('modalidad', 20)->nullable();
            $table->string('imparte', 20)->nullable();
            $table->foreignId('ponente_persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('ponente_nombre')->nullable();
        });

        DB::statement("ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_modalidad_check CHECK (modalidad IN ('presencial', 'en_linea', 'mixta'))");
        DB::statement("ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_imparte_check CHECK (imparte IN ('interna', 'externa'))");
        DB::statement(<<<'SQL'
            ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_ponente_coherente CHECK (
                (imparte IS NULL AND ponente_persona_id IS NULL AND proveedor_id IS NULL AND ponente_nombre IS NULL)
                OR (imparte = 'interna' AND proveedor_id IS NULL AND ponente_nombre IS NULL)
                OR (imparte = 'externa' AND ponente_persona_id IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::table('acciones_formativas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ponente_persona_id');
            $table->dropConstrainedForeignId('proveedor_id');
            $table->dropColumn(['modalidad', 'imparte', 'ponente_nombre']);
        });
    }
};
