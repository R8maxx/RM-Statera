<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cambia el rol de una cuenta y, con él, su alcance.
 *
 * Van juntos porque el alcance depende del rol: pasar a auditor sin decir qué
 * sistemas y hasta cuándo dejaría una cuenta de auditor viendo la organización
 * entera, y pasar de auditor a técnico con el filtro puesto dejaría a un
 * técnico sin ver la mitad de su trabajo.
 *
 * **El rol vive en una pivote de spatie** y cambiarlo no dispara ningún
 * `updated`, así que la traza se escribe aquí con su propio verbo.
 */
final class CambiarRol
{
    public function __construct(
        private readonly ResponsablesDeSeguridad $responsables,
        private readonly AlcanceDeCuenta $alcance,
        private readonly RegistroTraza $traza,
    ) {}

    /**
     * @param  list<int>  $sistemas
     */
    public function __invoke(User $quien, User $cuenta, Rol $rol, array $sistemas = [], ?Carbon $accesoHasta = null): void
    {
        $anterior = $cuenta->rol();

        if ($anterior === Rol::ResponsableSeguridad && $rol !== Rol::ResponsableSeguridad) {
            if ($cuenta->is($quien)) {
                throw OperacionDeCuentaNoPermitida::sobreSiMisma();
            }

            if (! $this->responsables->quedaOtro($cuenta)) {
                throw OperacionDeCuentaNoPermitida::ultimoResponsable();
            }
        }

        DB::transaction(function () use ($cuenta, $rol, $sistemas, $accesoHasta, $anterior): void {
            if ($anterior !== $rol) {
                $cuenta->syncRoles([$rol->value]);

                $this->traza->evento(
                    $cuenta,
                    AccionAuditada::RolCambiado,
                    ['rol' => $anterior?->value],
                    ['rol' => $rol->value],
                );
            }

            $this->alcance->fijar($cuenta, $rol, $sistemas, $accesoHasta);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
