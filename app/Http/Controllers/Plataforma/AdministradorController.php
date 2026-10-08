<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Plataforma\AdministradoresDePlataforma;
use App\Domain\Plataforma\AltaAdministrador;
use App\Domain\Plataforma\Enums\PerfilPlataforma;
use App\Domain\Plataforma\Excepciones\AdministracionNoPermitida;
use App\Domain\Usuario\EnviarInvitacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\GestionarAdministradorRequest;
use App\Http\Requests\InvitarAdministradorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quién administra la plataforma (punto 49): la lista, invitar, cambiar el
 * perfil y retirar. Las reglas viven en `AdministradoresDePlataforma`.
 *
 * `{administrador}` llega como entero y se busca entre las cuentas de la
 * plataforma: un id de una cuenta de cliente responde 404.
 */
class AdministradorController extends Controller
{
    public function index(Request $request, AdministradoresDePlataforma $administradores): Response
    {
        /** @var User $yo */
        $yo = $request->user();

        return Inertia::render('plataforma/administradores/Index', [
            'administradores' => $administradores->todos()->map(static function (User $cuenta) use ($yo): array {
                $estado = $cuenta->estadoCuenta();

                return [
                    'id' => $cuenta->id,
                    'nombre' => $cuenta->name,
                    'email' => $cuenta->email,
                    'perfil' => $cuenta->perfil_plataforma?->value,
                    'perfilEtiqueta' => $cuenta->perfil_plataforma?->etiqueta(),
                    'dosFactores' => $cuenta->dosFactoresConfirmado(),
                    'ultimoAcceso' => $cuenta->ultimo_acceso_en?->toIso8601String(),
                    'organizacion' => $cuenta->organizacion?->nombre,
                    'estado' => [
                        'valor' => $estado->value,
                        'etiqueta' => $estado->etiqueta(),
                        'tono' => $estado->tono(),
                        'icono' => $estado->icono(),
                    ],
                    'esLaPropia' => $cuenta->is($yo),
                ];
            })->values()->all(),
            'perfiles' => array_map(static fn (PerfilPlataforma $perfil): array => [
                'valor' => $perfil->value,
                'etiqueta' => $perfil->etiqueta(),
                'descripcion' => $perfil->descripcion(),
            ], PerfilPlataforma::cases()),
        ]);
    }

    public function store(InvitarAdministradorRequest $request, AltaAdministrador $alta): RedirectResponse
    {
        $cuenta = $alta((string) $request->validated('name'), (string) $request->validated('email'), $request->perfil());

        Inertia::flash('exito', "Invitación enviada a {$cuenta->email}. Caduca en ".EnviarInvitacion::diasDeValidez().' días.');

        return to_route('plataforma.administradores.index');
    }

    public function perfil(GestionarAdministradorRequest $request, int $administrador, AdministradoresDePlataforma $administradores): RedirectResponse
    {
        /** @var User $yo */
        $yo = $request->user();

        try {
            $administradores->cambiarPerfil($yo, $administradores->encontrar($administrador), $request->perfil());
        } catch (AdministracionNoPermitida $error) {
            return back()->withErrors(['administrador' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Perfil cambiado.');

        return to_route('plataforma.administradores.index');
    }

    public function retirar(GestionarAdministradorRequest $request, int $administrador, AdministradoresDePlataforma $administradores): RedirectResponse
    {
        /** @var User $yo */
        $yo = $request->user();
        $cuenta = $administradores->encontrar($administrador);

        try {
            $administradores->retirar($yo, $cuenta);
        } catch (AdministracionNoPermitida $error) {
            return back()->withErrors(['administrador' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$cuenta->name} ya no administra la plataforma.");

        return to_route('plataforma.administradores.index');
    }
}
