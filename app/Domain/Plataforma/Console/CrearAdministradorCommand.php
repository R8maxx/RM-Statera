<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Console;

use App\Domain\Plataforma\AltaAdministrador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * La única puerta para crear a quien administra la plataforma (punto 41).
 *
 * Desde la consola y no desde una pantalla: crear administradores es cosa de
 * quien tiene el servidor, la misma frontera que ya protege las copias y el
 * catálogo. El administrador recibe una invitación y fija él su contraseña;
 * este comando no la conoce nunca.
 */
final class CrearAdministradorCommand extends Command
{
    protected $signature = 'plataforma:administrador
        {email : El correo con el que entrará}
        {nombre : Cómo se le nombra en la traza y en la interfaz}';

    protected $description = 'Da de alta a una persona que administra la plataforma y le envía la invitación';

    public function handle(AltaAdministrador $alta): int
    {
        $datos = ['email' => $this->argument('email'), 'nombre' => $this->argument('nombre')];

        $validacion = Validator::make($datos, [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
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

        $cuenta = $alta($validos['nombre'], $validos['email']);

        $this->components->info("Invitación enviada a {$cuenta->email}. Tendrá que activar el segundo factor al entrar.");

        return self::SUCCESS;
    }
}
