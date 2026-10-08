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
 * Puede ser además usuario de una organización (punto 45): si el correo ya es
 * de una cuenta de cliente, `promover()` la convierte en vez de crear otra.
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

    /**
     * Si ya hay una cuenta con ese correo —de un cliente—, se promueve en vez de
     * crear otra (punto 45): conserva su organización y su rol, y gana la
     * plataforma. No se le manda invitación, porque ya entra.
     */
    public function promover(User $cuenta): User
    {
        if ($cuenta->esPlataforma()) {
            return $cuenta;
        }

        $cuenta->forceFill(['es_plataforma' => true])->save();

        $this->traza->registrar(AccionPlataforma::AdministradorPromovido, $cuenta->organizacion, [
            'name' => $cuenta->name,
            'email' => $cuenta->email,
        ], $cuenta->id);

        return $cuenta;
    }

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
