<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entra `incidente` como origen de una oportunidad de mejora. Quinto caso.
 *
 * **La lección aprendida de un incidente es la fuente clásica de una mejora**, y
 * hasta aquí acabaría como «iniciativa propia» — que es exactamente el «elegir el
 * que menos mal suena» que deja el campo sin significar nada. Un incidente que no
 * incumple nada —el correo fraudulento que alguien detectó y reportó bien— no
 * abre una no conformidad, y aun así deja una idea para la próxima vez.
 *
 * Literales en las dos direcciones, como el resto de migraciones de esta familia:
 * enumerar desde el enum haría que `migrate:fresh` incluyera el valor nuevo aunque
 * faltara esta migración y ningún test se pondría rojo.
 *
 * El `down()` pasa las mejoras del origen nuevo a `propia` en vez de borrarlas,
 * como hizo la de `no_conformidad` en tareas: una mejora es trabajo apuntado, con
 * su histórico y puede que con sus tareas detrás. Y va por
 * `ContextoOrganizacion::comoMantenimiento()`, porque una migración no tiene
 * petición ni usuario: sin eso RLS deniega por defecto, el `update` afecta a cero
 * filas **sin fallar** y el `ALTER TABLE` muere con «is violated by some row».
 */
return new class extends Migration
{
    private const ANTIGUOS = "'auditoria', 'revision_direccion', 'indicador', 'propia'";

    private const NUEVOS = "'auditoria', 'revision_direccion', 'indicador', 'incidente', 'propia'";

    public function up(): void
    {
        $nuevos = self::NUEVOS;

        DB::statement('ALTER TABLE mejoras DROP CONSTRAINT mejoras_origen_check');
        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_origen_check CHECK (origen IN ({$nuevos}))");
    }

    public function down(): void
    {
        $antiguos = self::ANTIGUOS;

        app(ContextoOrganizacion::class)->comoMantenimiento(
            static fn () => DB::table('mejoras')->where('origen', 'incidente')->update(['origen' => 'propia']),
        );

        DB::statement('ALTER TABLE mejoras DROP CONSTRAINT mejoras_origen_check');
        DB::statement("ALTER TABLE mejoras ADD CONSTRAINT mejoras_origen_check CHECK (origen IN ({$antiguos}))");
    }
};
