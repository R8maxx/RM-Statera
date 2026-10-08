<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Console;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AltaAdministrador;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\UnirAdministrador;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * La única puerta para crear a quien administra la plataforma (punto 41).
 *
 * Desde la consola y no desde una pantalla: crear administradores es cosa de
 * quien tiene el servidor, la misma frontera que ya protege las copias y el
 * catálogo. El administrador recibe una invitación y fija él su contraseña;
 * este comando no la conoce nunca. Si el correo ya es de una cuenta de cliente,
 * la promueve en lugar de crear otra (punto 45).
 */
final class CrearAdministradorCommand extends Command
{
    protected $signature = 'plataforma:administrador
        {email : El correo con el que entrará}
        {nombre : Cómo se le nombra en la traza y en la interfaz}
        {--organizacion= : Id de una organización de la que hacerle además usuario (punto 45)}
        {--rol=tecnico : Su rol en esa organización: responsable_seguridad o tecnico}
        {--perfil=administracion : Su perfil en la plataforma: administracion o comercial (punto 48)}';

    protected $description = 'Da de alta a una persona que administra la plataforma y le envía la invitación';

    public function handle(AltaAdministrador $alta, UnirAdministrador $unir): int
    {
        $datos = ['email' => $this->argument('email'), 'nombre' => $this->argument('nombre')];

        $validacion = Validator::make($datos, [
            'email' => ['required', 'email', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        /** @var array{email: string, nombre: string} $validos */
        $validos = $validacion->validated();

        $perfil = PerfilPlataforma::tryFrom((string) $this->option('perfil'));

        if ($perfil === null) {
            $this->components->error('El perfil es administracion o comercial.');

            return self::FAILURE;
        }

        $organizacion = null;
        $rol = Rol::tryFrom((string) $this->option('rol'));

        if ($this->option('organizacion') !== null) {
            $organizacion = Organizacion::query()->find((int) $this->option('organizacion'));

            if ($organizacion === null || $rol === null || $rol === Rol::Auditor) {
                $this->components->error('Hace falta una organización que exista y un rol de responsable_seguridad o tecnico: el auditor externo no puede ser de la plataforma.');

                return self::FAILURE;
            }
        }

        // Por el proveedor del guard, como el login: buscar por correo es
        // justo lo que hace quien todavía no sabe de qué organización es nadie.
        $existente = Auth::guard('web')->getProvider()->retrieveByCredentials(['email' => $validos['email']]);

        if ($existente instanceof User && ! $existente->esPlataforma()) {
            if ($organizacion !== null) {
                $this->components->error("{$existente->email} ya es de una organización: sólo se puede ser de una.");

                return self::FAILURE;
            }

            $alta->promover($existente, $perfil);
            $this->components->info("{$existente->email} ya tenía cuenta: ahora administra también la plataforma, y conserva su organización y su rol.");

            return self::SUCCESS;
        }

        $cuenta = $existente instanceof User ? $existente : $alta($validos['nombre'], $validos['email'], $perfil);

        if ($existente instanceof User && $organizacion === null) {
            $this->components->warn("{$existente->email} ya administra la plataforma.");

            return self::SUCCESS;
        }

        if ($organizacion !== null) {
            if ($cuenta->organizacion_id !== null) {
                $this->components->error("{$cuenta->email} ya es de una organización: sólo se puede ser de una.");

                return self::FAILURE;
            }

            $unir($cuenta, $organizacion, $rol);
            $this->components->info("{$cuenta->email} es ahora además usuario de {$organizacion->nombre}, con el rol de ".mb_strtolower($rol->etiqueta()).'.');
        }

        if (! $existente instanceof User) {
            $this->components->info("Invitación enviada a {$cuenta->email}. Tendrá que activar el segundo factor al entrar.");
        }

        return self::SUCCESS;
    }
}
