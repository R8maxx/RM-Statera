<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Usuario\EnviarInvitacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Da de alta a quien administra la plataforma (punto 41).
 *
 * **Sólo desde la consola** (`plataforma:administrador`): no hay pantalla que
 * cree administradores. Quien puede crearlos es quien tiene acceso al
 * servidor, que es la misma frontera que ya protege las copias y el catálogo.
 *
 * Entra igual que cualquier cuenta: una invitación con enlace, una contraseña
 * que nadie más conoce y el segundo factor obligatorio en cuanto pisa
 * `/plataforma`.
 */
final class AltaAdministrador
{
    public function __construct(
        private readonly EnviarInvitacion $enviar,
        private readonly TrazaPlataforma $traza,
    ) {}

    public function __invoke(string $nombre, string $email): User
    {
        $cuenta = DB::transaction(function () use ($nombre, $email): User {
            // Sin organización, y no por olvido: es lo que la distingue, y el
            // `CHECK` de la base impide que tenga las dos cosas.
            $cuenta = new User;
            $cuenta->forceFill([
                'name' => $nombre,
                'email' => $email,
                'password' => Str::password(64),
                'organizacion_id' => null,
                'es_plataforma' => true,
                'invitada_en' => now(),
                'activada_en' => null,
            ])->save();

            $this->traza->registrar(AccionPlataforma::AdministradorCreado, null, [
                'name' => $cuenta->name,
                'email' => $cuenta->email,
            ], $cuenta->id);

            return $cuenta;
        });

        ($this->enviar)($cuenta);

        return $cuenta;
    }
}
