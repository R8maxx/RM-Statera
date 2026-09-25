<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Models\User;

/**
 * A quién se le puede encargar algo de un módulo.
 *
 * **A quien puede escribir en él y puede entrar.** El desplegable de
 * «Responsable» listaba todas las cuentas de la organización, así que ofrecía
 * al auditor externo como responsable de remediar una vulnerabilidad: alguien
 * que no puede tocarla y que, además, no debe, porque audita lo que se hace.
 * Tampoco se ofrece una cuenta desactivada ni una con el acceso caducado.
 *
 * **El que ya está asignado se conserva** aunque haya dejado de cumplirlo: sin
 * él, editar la ficha vaciaría el campo en silencio, que es peor que dejar a la
 * vista una asignación que hay que cambiar.
 *
 * El permiso se comprueba con `can()` y no en SQL: pasa por el rol y por el
 * equipo de spatie, que es donde vive la regla, y una organización tiene
 * decenas de cuentas y no miles.
 *
 * `User` está fuera de las tres capas, así que la organización se filtra a mano.
 */
final class CuentasAsignables
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    /** @return list<array{valor: string, etiqueta: string}> */
    public function opciones(Permiso $permiso, ?int $actual = null): array
    {
        return array_map(
            static fn (User $usuario): array => ['valor' => (string) $usuario->id, 'etiqueta' => $usuario->name],
            $this->cuentas($permiso, $actual),
        );
    }

    public function admite(int $id, Permiso $permiso, ?int $actual = null): bool
    {
        foreach ($this->cuentas($permiso, $actual) as $usuario) {
            if ($usuario->id === $id) {
                return true;
            }
        }

        return false;
    }

    /** @return list<User> */
    private function cuentas(Permiso $permiso, ?int $actual): array
    {
        return User::query()
            ->where('organizacion_id', $this->contexto->idObligatorio())
            ->orderBy('name')
            ->get()
            ->filter(static fn (User $usuario): bool => $usuario->id === $actual || (
                $usuario->desactivada_en === null
                && ($usuario->acceso_hasta === null || ! $usuario->acceso_hasta->isBefore(today()))
                && $usuario->can($permiso->value)
            ))
            ->values()
            ->all();
    }
}
