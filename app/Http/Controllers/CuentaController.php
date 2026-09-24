<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Domain\Usuario\CambiarRol;
use App\Domain\Usuario\DesactivarCuenta;
use App\Domain\Usuario\Enums\EstadoCuenta;
use App\Domain\Usuario\EnviarInvitacion;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Domain\Usuario\InvitarCuenta;
use App\Domain\Usuario\Models\CuentaSistema;
use App\Domain\Usuario\ReactivarCuenta;
use App\Domain\Usuario\ResumenCuentas;
use App\Domain\Usuario\VincularPersona;
use App\Http\Requests\DesactivarCuentaRequest;
use App\Http\Requests\GuardarCuentaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\CuentaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las cuentas de la organización (§ 4.19): quién entra, con qué rol y, para el
 * auditor externo, qué ve y hasta cuándo.
 *
 * **Todo va con `cuentas.gestionar`**, que sólo tiene el responsable de
 * seguridad: no hay pantalla de lectura para los otros dos roles, y la de cada
 * uno es `/perfil`.
 *
 * El controlador valida, delega y devuelve: las reglas —la del último
 * responsable, la de no tocarse a uno mismo, la del auditor con alcance— viven
 * en `app/Domain/Usuario/` y llegan aquí como `OperacionDeCuentaNoPermitida`.
 *
 * El `{cuenta}` de la ruta se resuelve acotado a la organización en
 * `routes/web.php`, porque `User` no tiene scope global.
 */
class CuentaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, CuentaRecurso $recurso, ResumenCuentas $resumen): Response
    {
        $organizacionId = (int) $request->user()?->organizacion_id;

        return Inertia::render('cuentas/Index', [
            ...$this->tabla($recurso, $request),
            'invitadas' => $resumen->invitadas($organizacionId),
            'sinDosFactores' => $resumen->sinDosFactores($organizacionId),
            'auditoresSinAlcance' => $resumen->auditoresSinAlcance($organizacionId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cuentas/Formulario', [
            'cuenta' => null,
            ...$this->opciones(null),
        ]);
    }

    public function store(GuardarCuentaRequest $request, InvitarCuenta $invitar): RedirectResponse
    {
        try {
            $cuenta = $invitar(
                nombre: (string) $request->validated('name'),
                email: (string) $request->validated('email'),
                rol: $request->rol(),
                sistemas: $request->sistemas(),
                accesoHasta: $request->accesoHasta(),
                persona: $request->persona(),
            );
        } catch (OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['rol' => $error->getMessage()])->withInput();
        }

        Inertia::flash('exito', "Invitación enviada a {$cuenta->email}. Caduca en ".EnviarInvitacion::diasDeValidez().' días.');

        return to_route('cuentas.show', $cuenta);
    }

    public function show(Request $request, User $cuenta): Response
    {
        $estado = $cuenta->estadoCuenta();
        $persona = Persona::query()->where('user_id', $cuenta->id)->first();

        return Inertia::render('cuentas/Ficha', [
            'cuenta' => [
                'id' => $cuenta->id,
                'nombre' => $cuenta->name,
                'email' => $cuenta->email,
                'rol' => $cuenta->rol()?->value,
                'rolEtiqueta' => $cuenta->rol()?->etiqueta(),
                'rolDescripcion' => $cuenta->rol()?->descripcion(),
                'estado' => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                    'descripcion' => $estado->descripcion(),
                    'tono' => $estado->tono(),
                    'icono' => $estado->icono(),
                ],
                'dosFactores' => $cuenta->dosFactoresConfirmado(),
                'accesoHasta' => $cuenta->acceso_hasta?->toDateString(),
                'invitadaEn' => $cuenta->invitada_en?->toIso8601String(),
                'activadaEn' => $cuenta->activada_en?->toIso8601String(),
                'desactivadaEn' => $cuenta->desactivada_en?->toIso8601String(),
                'motivoDesactivacion' => $cuenta->motivo_desactivacion,
                'ultimoAcceso' => $cuenta->ultimo_acceso_en?->toIso8601String(),
                'persona' => $persona === null ? null : ['id' => $persona->id, 'nombre' => $persona->nombre],
                'sistemas' => $this->sistemasDe($cuenta),
                'sinAlcance' => $cuenta->rol() === Rol::Auditor && $this->sistemasDe($cuenta) === [],
                'esLaPropia' => $cuenta->is($request->user()),
            ],
            'actividad' => $this->actividad($cuenta),
        ]);
    }

    public function edit(User $cuenta): Response
    {
        return Inertia::render('cuentas/Formulario', [
            'cuenta' => [
                'id' => $cuenta->id,
                'nombre' => $cuenta->name,
                'email' => $cuenta->email,
                'rol' => $cuenta->rol()?->value,
                'sistemas' => array_map(
                    static fn (array $sistema): string => (string) $sistema['id'],
                    $this->sistemasDe($cuenta),
                ),
                'accesoHasta' => $cuenta->acceso_hasta?->toDateString(),
                'personaId' => Persona::query()->where('user_id', $cuenta->id)->value('id'),
            ],
            ...$this->opciones($cuenta),
        ]);
    }

    public function update(
        GuardarCuentaRequest $request,
        User $cuenta,
        CambiarRol $cambiar,
        VincularPersona $vincular,
    ): RedirectResponse {
        /** @var User $quien */
        $quien = $request->user();

        try {
            DB::transaction(function () use ($request, $cuenta, $cambiar, $vincular, $quien): void {
                $cambiar($quien, $cuenta, $request->rol(), $request->sistemas(), $request->accesoHasta());
                $vincular($cuenta, $request->persona());
            });
        } catch (OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['rol' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Cuenta actualizada.');

        return to_route('cuentas.show', $cuenta);
    }

    public function desactivar(DesactivarCuentaRequest $request, User $cuenta, DesactivarCuenta $desactivar): RedirectResponse
    {
        /** @var User $quien */
        $quien = $request->user();

        try {
            $desactivar($quien, $cuenta, $request->validated('motivo'));
        } catch (OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['cuenta' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$cuenta->name} ya no entra en Statera. Lo que hizo sigue a su nombre.");

        return to_route('cuentas.show', $cuenta);
    }

    public function reactivar(User $cuenta, ReactivarCuenta $reactivar): RedirectResponse
    {
        $reactivar($cuenta);

        Inertia::flash('exito', match ($cuenta->estadoCuenta()) {
            EstadoCuenta::Caducada => 'Cuenta reactivada, pero su fecha de fin ya pasó: amplíala para que pueda entrar.',
            // Desactivar anuló el enlace del correo, así que sin reenviar no entra.
            EstadoCuenta::Invitada => 'Cuenta reactivada. La invitación se anuló al desactivarla: reenvíala para que pueda entrar.',
            default => 'Cuenta reactivada.',
        });

        return to_route('cuentas.show', $cuenta);
    }

    public function reenviar(User $cuenta, EnviarInvitacion $enviar): RedirectResponse
    {
        try {
            $enviar($cuenta);
        } catch (OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['cuenta' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Invitación reenviada a {$cuenta->email}. El enlace anterior ya no sirve.");

        return to_route('cuentas.show', $cuenta);
    }

    /**
     * Lo que el formulario ofrece.
     *
     * Las personas, sólo las que no tienen cuenta —más la de ésta—: el enlace es
     * único, y ofrecer una ya enlazada es invitar a un error.
     *
     * @return array<string, mixed>
     */
    private function opciones(?User $cuenta): array
    {
        return [
            'roles' => array_map(static fn (Rol $rol): array => [
                'valor' => $rol->value,
                'etiqueta' => $rol->etiqueta(),
                'descripcion' => $rol->descripcion(),
            ], Rol::cases()),
            'sistemas' => Sistema::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(static fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} · {$sistema->nombre}",
                ])->values()->all(),
            'personas' => Persona::query()
                ->where(fn ($consulta) => $consulta
                    ->whereNull('user_id')
                    ->when($cuenta !== null, fn ($propia) => $propia->orWhere('user_id', $cuenta?->id)))
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(static fn (Persona $persona): array => [
                    'valor' => (string) $persona->id,
                    'etiqueta' => $persona->nombre,
                ])->values()->all(),
        ];
    }

    /** @return list<array{id: int, codigo: string, nombre: string}> */
    private function sistemasDe(User $cuenta): array
    {
        return CuentaSistema::query()
            ->where('user_id', $cuenta->id)
            ->with('sistema:id,codigo,nombre')
            ->get()
            ->filter(static fn (CuentaSistema $fila): bool => $fila->sistema !== null)
            ->map(static fn (CuentaSistema $fila): array => [
                'id' => $fila->sistema_id,
                'codigo' => (string) $fila->sistema?->codigo,
                'nombre' => (string) $fila->sistema?->nombre,
            ])
            ->sortBy('codigo')
            ->values()
            ->all();
    }

    /**
     * Lo último que ha pasado con la cuenta, de la traza: entradas, cambios de
     * rol, desactivaciones. Es lo que se mira cuando alguien pregunta «¿desde
     * cuándo tiene este rol?», y por eso se pinta con el mismo histórico que
     * las máquinas de estados.
     *
     * @return list<array{id: int, anterior: null, nuevo: string, tono: string, icono: string, usuario: ?string, nota: ?string, fecha: string, evento: true}>
     */
    private function actividad(User $cuenta): array
    {
        return EventoAuditoria::query()
            ->where('entidad', class_basename(User::class))
            ->where('entidad_id', $cuenta->id)
            ->with('usuario:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (EventoAuditoria $evento): array => [
                'id' => $evento->id,
                'anterior' => null,
                'nuevo' => $evento->accion->etiqueta(),
                'tono' => $evento->accion->tono(),
                'icono' => $evento->accion->icono(),
                /*
                 * Sin autor, `HistoricoTransiciones` pone «recálculo del sistema»,
                 * que aquí sería falso: quien acepta una invitación o falla una
                 * contraseña no ha entrado todavía, y es la propia cuenta —o
                 * alguien con su correo—.
                 */
                'usuario' => $evento->usuario->name ?? match ($evento->accion) {
                    AccionAuditada::IntentoFallido => 'desde '.($evento->ip ?? 'una dirección desconocida'),
                    default => $cuenta->name,
                },
                'nota' => $this->detalle($evento),
                'fecha' => $evento->created_at->toIso8601String(),
                'evento' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Una línea legible de lo que cambió. Sólo para lo que la ficha sabe
     * contar; el resto se queda en el nombre de la acción.
     */
    private function detalle(EventoAuditoria $evento): ?string
    {
        $antes = $evento->valor_anterior ?? [];
        $despues = $evento->valor_nuevo ?? [];

        return match ($evento->accion) {
            AccionAuditada::RolCambiado => sprintf(
                '%s → %s',
                Rol::tryFrom((string) ($antes['rol'] ?? ''))?->etiqueta() ?? 'Sin rol',
                Rol::tryFrom((string) ($despues['rol'] ?? ''))?->etiqueta() ?? 'Sin rol',
            ),
            AccionAuditada::Creado => isset($despues['rol'])
                ? 'Invitada como '.mb_strtolower(Rol::tryFrom((string) $despues['rol'])?->etiqueta() ?? (string) $despues['rol'])
                : null,
            AccionAuditada::Actualizado => match (true) {
                array_key_exists('desactivada_en', $despues) && $despues['desactivada_en'] !== null => 'Desactivada'
                    .(isset($despues['motivo_desactivacion']) ? ': '.$despues['motivo_desactivacion'] : ''),
                array_key_exists('desactivada_en', $despues) => 'Reactivada',
                array_key_exists('activada_en', $despues) => 'Aceptó la invitación',
                array_key_exists('sistemas', $despues) => 'Alcance o fecha de fin cambiados'
                    .(isset($despues['acceso_hasta'])
                        ? ' · acceso hasta el '.Carbon::parse((string) $despues['acceso_hasta'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
                        : ''),
                default => null,
            },
            default => null,
        };
    }
}
