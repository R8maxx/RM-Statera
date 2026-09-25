<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las tareas pueden nacer de una vulnerabilidad (invariante 8, `op.exp.4`):
 * aplicar el parche, cambiar la configuración, sustituir el equipo sin soporte.
 *
 * El `down()` las pasa a `propia` y no las borra, como las anteriores: de dónde
 * venían lo sigue diciendo `vulnerabilidad_tarea`.
 */
return new class extends Migration
{
    private const ANTIGUOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'continuidad', 'proveedor', 'propia'";

    private const NUEVOS = "'hallazgo', 'no_conformidad', 'mejora', 'riesgo', 'brecha_implantacion', 'contexto', 'objetivo', 'incidente', 'revision_direccion', 'continuidad', 'proveedor', 'vulnerabilidad', 'propia'";

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
            static fn () => DB::table('tareas')->where('origen', 'vulnerabilidad')->update(['origen' => 'propia']),
        );

        DB::statement('ALTER TABLE tareas DROP CONSTRAINT tareas_origen_check');
        DB::statement("ALTER TABLE tareas ADD CONSTRAINT tareas_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
