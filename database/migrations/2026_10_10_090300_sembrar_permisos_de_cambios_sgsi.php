<?php

declare(strict_types=1);

use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Da los permisos de los cambios del SGSI (cláusula 6.3) a los roles de las
 * organizaciones que ya existen.
 *
 * Lo mismo que `sembrar_permisos_de_vulnerabilidades`, y por lo mismo: un
 * permiso nuevo en el enum no llega solo a la base, y la suite no lo ve porque
 * siembra en cada alta.
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
            ->whereIn('name', ['cambios_sgsi.ver', 'cambios_sgsi.gestionar', 'cambios_sgsi.aprobar'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
