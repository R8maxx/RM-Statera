<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AltaOrganizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Excepciones\AltaNoPermitida;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\EnviarInvitacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\AltaOrganizacionRequest;
use App\Http\Requests\CambiarSuscripcionRequest;
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
        return Inertia::render('plataforma/organizaciones/Crear', ['planes' => $this->planesActivos()]);
    }

    public function store(AltaOrganizacionRequest $request, AltaOrganizacion $alta): RedirectResponse
    {
        try {
            $organizacion = $alta(
                $request->ficha(),
                (string) $request->validated('responsable_nombre'),
                (string) $request->validated('responsable_email'),
                $request->plan(),
                $request->venceEn(),
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
            'suscripcion' => $this->resumenSuscripcion($organizacion),
            'planes' => $this->planesActivos($organizacion->plan_id),
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

    public function suscripcion(CambiarSuscripcionRequest $request, Organizacion $organizacion, CambiarSuscripcion $cambiar): RedirectResponse
    {
        $cambiar($organizacion, $request->plan(), $request->venceEn(), $request->validated('motivo'));

        Inertia::flash('exito', 'Suscripción guardada.');

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    /**
     * Los planes que se pueden elegir: los activos y, si hay, el que ya tiene.
     * Retirar un plan no se lo quita a quien lo tiene.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function planesActivos(?int $actual = null): array
    {
        return Plan::query()
            ->where(fn ($consulta) => $consulta->where('activo', true)->when($actual !== null, fn ($o) => $o->orWhere('id', $actual)))
            ->orderBy('nombre')
            ->get()
            ->map(static fn (Plan $plan): array => ['valor' => (string) $plan->id, 'etiqueta' => $plan->nombre])
            ->values()
            ->all();
    }

    /**
     * @return array{planId: ?int, plan: ?string, limiteCuentas: ?int, limiteSistemas: ?int, cuentasOcupadas: int, iniciaEn: ?string, venceEn: ?string, graciaHasta: ?string, estado: array{valor: string, etiqueta: string, tono: string, icono: string}, historico: list<array{id: int, de: ?string, a: ?string, venceEn: ?string, motivo: ?string, autor: ?string, fecha: string}>}
     */
    private function resumenSuscripcion(Organizacion $organizacion): array
    {
        $organizacion->loadMissing('plan');
        $estado = $organizacion->estadoSuscripcion();

        return [
            'planId' => $organizacion->plan_id,
            'plan' => $organizacion->plan?->nombre,
            'limiteCuentas' => $organizacion->plan?->limite_cuentas,
            'limiteSistemas' => $organizacion->plan?->limite_sistemas,
            'cuentasOcupadas' => app(LimitesDelPlan::class)->cuentasOcupadas($organizacion),
            'iniciaEn' => $organizacion->suscripcion_inicia_en?->toIso8601String(),
            'venceEn' => $organizacion->suscripcion_vence_en?->toIso8601String(),
            'graciaHasta' => EstadoSuscripcion::finDeGracia($organizacion)?->toIso8601String(),
            'estado' => [
                'valor' => $estado->value,
                'etiqueta' => $estado->etiqueta(),
                'tono' => $estado->tono(),
                'icono' => $estado->icono(),
            ],
            'historico' => TransicionSuscripcion::query()
                ->where('organizacion_afectada_id', $organizacion->id)
                ->with(['planAnterior:id,nombre', 'planNuevo:id,nombre', 'usuario:id,name'])
                ->latest('created_at')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static fn (TransicionSuscripcion $transicion): array => [
                    'id' => $transicion->id,
                    'de' => $transicion->planAnterior?->nombre,
                    'a' => $transicion->planNuevo?->nombre,
                    'venceEn' => $transicion->vence_en_nuevo?->toIso8601String(),
                    'motivo' => $transicion->motivo,
                    'autor' => $transicion->usuario?->name,
                    'fecha' => $transicion->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
