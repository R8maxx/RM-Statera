<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los roles y permisos de una organización. Idempotente.
 *
 * Los permisos son globales —no llevan `organizacion_id`, igual que el
 * catálogo— y los roles sí van por organización: `teams = true` con
 * `team_foreign_key = organizacion_id`. Que el reparto sea así no es capricho de
 * spatie: el vocabulario de permisos es el mismo para todos los clientes, y
 * quién los tiene es de cada uno.
 *
 * Se ejecuta al dar de alta una organización y cada vez que el catálogo de
 * permisos crezca: volver a pasarlo no duplica nada.
 */
final class SembrarRoles
{
    public function paraOrganizacion(Organizacion $organizacion): void
    {
        $this->permisosGlobales();

        $registrar = app(PermissionRegistrar::class);
        $anterior = $registrar->getPermissionsTeamId();

        // Los roles se crean dentro del «team» de la organización, así que hay
        // que apuntar el registrar ahí antes de tocarlos y devolverlo después.
        $registrar->setPermissionsTeamId($organizacion->id);

        try {
            foreach (Rol::cases() as $rol) {
                $modelo = Role::findOrCreate($rol->value, 'web');

                // `syncPermissions` y no `givePermissionTo`: si un rol pierde un
                // permiso en el código, tiene que perderlo también en la base.
                // Lo contrario deja privilegios de más que nadie ve.
                $modelo->syncPermissions(array_map(
                    static fn (Permiso $permiso): string => $permiso->value,
                    $rol->permisos(),
                ));
            }
        } finally {
            $registrar->setPermissionsTeamId($anterior);
            $registrar->forgetCachedPermissions();
        }
    }

    /** Todas las organizaciones comparten el vocabulario de permisos. */
    private function permisosGlobales(): void
    {
        foreach (Permiso::cases() as $permiso) {
            Permission::findOrCreate($permiso->value, 'web');
        }
    }
}
