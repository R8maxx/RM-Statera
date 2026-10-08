<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\AltaOrganizacion;
use App\Domain\Plataforma\BajaOrganizacion;
use App\Domain\Plataforma\CambiarSuscripcion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Excepciones\AltaNoPermitida;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Plataforma\Models\EventoPlataforma;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\EnviarInvitacion;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Http\Controllers\Controller;
use App\Http\Requests\AltaOrganizacionRequest;
use App\Http\Requests\BajaOrganizacionRequest;
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

    public function show(Request $request, Organizacion $organizacion): Response
    {
        $cuentas = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('plataforma/organizaciones/Ficha', [
            // `cliente` y `contrato`, no `organizacion` ni `suscripcion`: esos dos
            // son props compartidos que lee el layout, y uno de página con el
            // mismo nombre los pisa (el layout pintaba esta organización como la
            // activa y un aviso de vencimiento que no existía).
            'cliente' => [
                'id' => $organizacion->id,
                'nombre' => $organizacion->nombre,
                'razonSocial' => $organizacion->razon_social,
                'cif' => $organizacion->cif,
                'sector' => $organizacion->sector,
                'altaEn' => $organizacion->created_at?->toIso8601String(),
                // La ventana de soporte que abrió el cliente (punto 44), o nula.
                'soporteHasta' => $organizacion->soporteAbierto() ? $organizacion->soporte_hasta?->toIso8601String() : null,
                // La baja (punto 46): desde cuándo y por qué, o nula.
                'bajaEn' => $organizacion->baja_en?->toIso8601String(),
                'motivoBaja' => $organizacion->motivo_baja,
            ],
            'contrato' => $this->resumenSuscripcion($organizacion),
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
                    'esResponsable' => $cuenta->rol() === Rol::ResponsableSeguridad,
                    'dosFactores' => $cuenta->dosFactoresConfirmado(),
                    'plataforma' => $cuenta->esPlataforma(),
                    'estado' => [
                        'valor' => $estado->value,
                        'etiqueta' => $estado->etiqueta(),
                        'tono' => $estado->tono(),
                        'icono' => $estado->icono(),
                    ],
                ];
            })->values()->all(),
            // Los rescates pendientes de este cliente (punto 52), para quien
            // puede resolverlos.
            'rescates' => $request->user()?->puedeEnPlataforma(CapacidadPlataforma::CuentasRescatar) === true
                ? SolicitudPlataforma::query()
                    ->where('organizacion_afectada_id', $organizacion->id)
                    ->with(['organizacion:id,nombre', 'cuenta:id,name,email', 'solicitante:id,name', 'resolutor:id,name'])
                    ->orderByDesc('solicitada_en')
                    ->limit(10)
                    ->get()
                    ->map(fn (SolicitudPlataforma $solicitud): array => RescateController::solicitud($solicitud, $request->user()))
                    ->values()
                    ->all()
                : [],
            'invitadas' => $cuentas->filter(static fn (User $cuenta): bool => $cuenta->estadoCuenta() === EstadoCuenta::Invitada)->count(),
            'traza' => EventoPlataforma::query()
                ->where('organizacion_afectada_id', $organizacion->id)
                ->with('usuario:id,name')
                ->latest('created_at')
                // Un comando escribe varios en el mismo instante: que el
                // último anotado salga primero, no al azar.
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(static fn (EventoPlataforma $evento): array => [
                    'id' => $evento->id,
                    'accion' => $evento->accion->etiqueta(),
                    'resumen' => $evento->resumen(),
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
     * Dar de baja una organización (punto 46): nadie suyo entra y nada se
     * borra. Se deshace con `reactivar()`.
     */
    public function darDeBaja(BajaOrganizacionRequest $request, Organizacion $organizacion, BajaOrganizacion $baja): RedirectResponse
    {
        $baja->darDeBaja($organizacion, (string) $request->validated('motivo'));

        Inertia::flash('exito', "{$organizacion->nombre} está de baja. Nadie suyo entra, y todo lo que tiene se conserva.");

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    public function reactivar(Organizacion $organizacion, BajaOrganizacion $baja): RedirectResponse
    {
        $baja->reactivar($organizacion);

        Inertia::flash('exito', "{$organizacion->nombre} vuelve a estar activa.");

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    /**
     * Reenviar la invitación de una cuenta del cliente que no la aceptó.
     *
     * Hace falta sobre todo para el primer responsable: si su enlace caduca,
     * dentro no hay nadie que pueda reenviárselo. La cuenta se busca acotada a
     * la organización de la ruta, porque `users` no tiene scope ni RLS.
     */
    public function reenviar(Organizacion $organizacion, int $cuentaId, EnviarInvitacion $enviar, TrazaPlataforma $traza): RedirectResponse
    {
        $invitada = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->whereKey($cuentaId)
            ->firstOrFail();

        try {
            $enviar($invitada);
        } catch (OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['cuentas' => $error->getMessage()]);
        }

        $traza->registrar(AccionPlataforma::InvitacionReenviada, $organizacion, ['email' => $invitada->email]);

        Inertia::flash('exito', "Invitación reenviada a {$invitada->email}. El enlace anterior ya no sirve.");

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    /**
     * Los planes que se pueden elegir: los activos y, si hay, el que ya tiene.
     * Retirar un plan no se lo quita a quien lo tiene.
     *
     * Con sus límites y su gracia, para que la ficha diga qué saldrá de guardar
     * antes de guardarlo.
     *
     * @return list<array{valor: string, etiqueta: string, limiteCuentas: ?int, limiteSistemas: ?int, diasGracia: int}>
     */
    private function planesActivos(?int $actual = null): array
    {
        return Plan::query()
            ->where(fn ($consulta) => $consulta->where('activo', true)->when($actual !== null, fn ($o) => $o->orWhere('id', $actual)))
            ->orderBy('nombre')
            ->get()
            ->map(static fn (Plan $plan): array => [
                'valor' => (string) $plan->id,
                'etiqueta' => $plan->nombre,
                'limiteCuentas' => $plan->limite_cuentas,
                'limiteSistemas' => $plan->limite_sistemas,
                'diasGracia' => $plan->dias_gracia,
            ])
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
