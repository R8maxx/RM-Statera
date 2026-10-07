<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AltaOrganizacion;
use App\Domain\Plataforma\Excepciones\AltaNoPermitida;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\EnviarInvitacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\AltaOrganizacionRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\OrganizacionPlataformaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las organizaciones cliente, desde la plataforma (punto 41).
 *
 * **Sólo la ficha comercial**: nombre, identificación, cuentas y la traza de
 * lo que la plataforma ha hecho con ella. Nada de lo que el cliente guarda
 * dentro, que no se lee sin que él abra la puerta.
 *
 * `{organizacion}` se resuelve sin acotar, y es lo correcto: `organizaciones`
 * no tiene scope ni RLS —es la raíz del tenant— y estas rutas sólo las alcanza
 * quien administra la plataforma (`SoloPlataforma`).
 */
class OrganizacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, OrganizacionPlataformaRecurso $recurso): Response
    {
        return Inertia::render('plataforma/organizaciones/Index', $this->tabla($recurso, $request));
    }

    public function create(): Response
    {
        return Inertia::render('plataforma/organizaciones/Crear');
    }

    public function store(AltaOrganizacionRequest $request, AltaOrganizacion $alta): RedirectResponse
    {
        try {
            $organizacion = $alta(
                $request->ficha(),
                (string) $request->validated('responsable_nombre'),
                (string) $request->validated('responsable_email'),
            );
        } catch (AltaNoPermitida $error) {
            return back()->withErrors(['nombre' => $error->getMessage()])->withInput();
        }

        Inertia::flash(
            'exito',
            "{$organizacion->nombre} está dada de alta. La invitación del responsable caduca en ".EnviarInvitacion::diasDeValidez().' días.',
        );

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    public function show(Organizacion $organizacion): Response
    {
        $cuentas = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('plataforma/organizaciones/Ficha', [
            'organizacion' => [
                'id' => $organizacion->id,
                'nombre' => $organizacion->nombre,
                'razonSocial' => $organizacion->razon_social,
                'cif' => $organizacion->cif,
                'sector' => $organizacion->sector,
                'altaEn' => $organizacion->created_at?->toIso8601String(),
            ],
            // Quién entra y con qué rol, sin nada de lo que hace dentro. Es lo
            // que hace falta para saber a quién llamar y si alguien no aceptó.
            'cuentas' => $cuentas->map(static function (User $cuenta): array {
                $estado = $cuenta->estadoCuenta();

                return [
                    'id' => $cuenta->id,
                    'nombre' => $cuenta->name,
                    'email' => $cuenta->email,
                    'rol' => $cuenta->rol()?->etiqueta(),
                    'estado' => [
                        'valor' => $estado->value,
                        'etiqueta' => $estado->etiqueta(),
                        'tono' => $estado->tono(),
                        'icono' => $estado->icono(),
                    ],
                ];
            })->values()->all(),
            'invitadas' => $cuentas->filter(static fn (User $cuenta): bool => $cuenta->estadoCuenta() === EstadoCuenta::Invitada)->count(),
            'traza' => EventoPlataforma::query()
                ->where('organizacion_afectada_id', $organizacion->id)
                ->with('usuario:id,name')
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(static fn (EventoPlataforma $evento): array => [
                    'id' => $evento->id,
                    'accion' => $evento->accion->etiqueta(),
                    'autor' => $evento->usuario?->name,
                    'fecha' => $evento->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
