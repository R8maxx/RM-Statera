<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Excepciones\AdministracionNoPermitida;
use App\Domain\Usuario\DesactivarCuenta;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quién administra la plataforma, y cambiarle el perfil o retirarle (punto 49).
 *
 * **Una sola regla que no se rompe**, calcada de `ResponsablesDeSeguridad`: la
 * plataforma no se queda nunca sin alguien de perfil Administración que pueda
 * entrar. Sin él nadie da de alta administradores, rescata una cuenta ni ve la
 * salud del servicio, y la única salida sería la consola. Y nadie se cambia ni
 * se retira a sí mismo.
 *
 * **Retirar no borra**: quita la marca y el perfil. Si además no es de ninguna
 * organización, la cuenta se desactiva, porque ya no tendría nada que hacer en
 * Statera. Si es de una (punto 45), sigue siendo usuario de ella.
 *
 * Las consultas de `users` se acotan por `es_plataforma`: los administradores
 * no son de ningún cliente, así que no hay `organizacion_id` por el que acotar.
 */
final class AdministradoresDePlataforma
{
    public function __construct(
        private readonly TrazaPlataforma $traza,
        private readonly DesactivarCuenta $desactivar,
    ) {}

    /** @return Collection<int, User> */
    public function todos(): Collection
    {
        return User::query()
            ->where('es_plataforma', true)
            ->with('organizacion:id,nombre')
            ->orderBy('name')
            ->get();
    }

    public function encontrar(int $id): User
    {
        return User::query()->where('es_plataforma', true)->whereKey($id)->firstOrFail();
    }

    /** Si, quitando a esta cuenta, queda otro de Administración que entre. */
    public function quedaOtro(User $excepto): bool
    {
        return User::query()
            ->where('es_plataforma', true)
            ->where('perfil_plataforma', PerfilPlataforma::Administracion->value)
            ->whereKeyNot($excepto->id)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->exists();
    }

    public function cambiarPerfil(User $quien, User $cuenta, PerfilPlataforma $perfil): void
    {
        $anterior = $cuenta->perfil_plataforma;

        if ($anterior === $perfil) {
            return;
        }

        if ($cuenta->is($quien)) {
            throw AdministracionNoPermitida::sobreSiMismo();
        }

        if ($anterior === PerfilPlataforma::Administracion && ! $this->quedaOtro($cuenta)) {
            throw AdministracionNoPermitida::ultimoAdministrador();
        }

        DB::transaction(function () use ($cuenta, $perfil, $anterior): void {
            $cuenta->forceFill(['perfil_plataforma' => $perfil->value])->save();

            $this->traza->registrar(AccionPlataforma::AdministradorPerfilCambiado, null, [
                'email' => $cuenta->email,
                'de' => $anterior?->value,
                'a' => $perfil->value,
            ]);
        });
    }

    public function retirar(User $quien, User $cuenta): void
    {
        if ($cuenta->is($quien)) {
            throw AdministracionNoPermitida::sobreSiMismo();
        }

        if ($cuenta->perfil_plataforma === PerfilPlataforma::Administracion && ! $this->quedaOtro($cuenta)) {
            throw AdministracionNoPermitida::ultimoAdministrador();
        }

        DB::transaction(function () use ($quien, $cuenta): void {
            $perfil = $cuenta->perfil_plataforma;

            $cuenta->forceFill(['es_plataforma' => false, 'perfil_plataforma' => null])->save();

            // Sin organización ya no tiene nada que hacer en Statera. Con
            // ella, sigue siendo usuario suyo (punto 45).
            if ($cuenta->organizacion_id === null) {
                ($this->desactivar)($quien, $cuenta, 'Retirada de la plataforma');
            }

            $this->traza->registrar(AccionPlataforma::AdministradorRetirado, null, [
                'email' => $cuenta->email,
                'perfil' => $perfil?->value,
                'sigue_en_organizacion' => $cuenta->organizacion_id !== null,
            ]);
        });
    }
}
