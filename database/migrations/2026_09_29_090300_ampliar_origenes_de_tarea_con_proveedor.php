<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las tareas pueden nacer de un proveedor (§ 4.9): lo que una evaluación apta con
 * condiciones deja pendiente —firmar el encargo de tratamiento, pedir el informe
 * de auditoría— es trabajo con responsable y plazo.
 *
 * El `down()` las pasa a `propia` y no las borra, como las anteriores: es trabajo
 * real con su histórico, y de dónde venía lo sigue diciendo `proveedor_tarea`.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'continuidad', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'continuidad', 'proveedor', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        app(ContextoOrganizacion::class)->comoMantenimiento(
            static fn () => DB::table('tareas')->where('origen', 'proveedor')->update(['origen' => 'propia']),
        );

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
