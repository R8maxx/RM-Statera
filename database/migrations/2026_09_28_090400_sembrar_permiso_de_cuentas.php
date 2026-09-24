<?php

declare(strict_types=1);

use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Da `cuentas.gestionar` al responsable de seguridad de las organizaciones que ya
 * existen (§ 4.19).
 *
 * **Un permiso nuevo en el enum no llega solo a la base.** `SembrarRoles` sólo
 * corre al dar de alta una organización y desde el seeder, así que sin esto el
 * responsable de una organización ya creada recibe un 403 en la pantalla que
 * existe para él. Lo destapó el recorrido en el navegador, con la suite en
 * verde: los tests siembran los roles en cada alta.
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
        DB::table('permissions')->where('name', 'cuentas.gestionar')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
