<?php

declare(strict_types=1);

use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Da los permisos de vulnerabilidades (invariante 8) a los roles de las organizaciones que ya
 * existen.
 *
 * Lo mismo que `2026_09_28_090400_sembrar_permiso_de_cuentas`, y por lo mismo:
 * un permiso nuevo en el enum no llega solo a la base, y la suite no lo ve porque
 * siembra en cada alta.
 *
 * `SembrarRoles` es idempotente y sincroniza con el código, así que volver a
 * pasarlo no duplica nada. `organizaciones` no lleva RLS y las tablas de spatie
 * tampoco, por eso no hace falta el modo mantenimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sembrar = app(SembrarRoles::class);

        foreach (Organizacion::query()->orderBy('id')->get() as $organizacion) {
            $sembrar->paraOrganizacion($organizacion);
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['vulnerabilidades.ver', 'vulnerabilidades.gestionar', 'vulnerabilidades.aceptar'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
