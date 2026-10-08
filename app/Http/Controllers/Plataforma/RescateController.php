<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Excepciones\RescateNoPermitido;
use App\Domain\Plataforma\Models\SolicitudPlataforma;
use App\Domain\Plataforma\Rescate\ResolverRescate;
use App\Domain\Plataforma\Rescate\SolicitarRescate;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitarRescateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rescatar cuentas de un cliente, con dos personas (punto 52): pedir, ejecutar
 * y rechazar. Las reglas viven en `SolicitarRescate` y `ResolverRescate`.
 */
class RescateController extends Controller
{
    public function index(Request $request, ResolverRescate $resolver): Response
    {
        /** @var User $yo */
        $yo = $request->user();

        return Inertia::render('plataforma/solicitudes/Index', [
            'solicitudes' => SolicitudPlataforma::query()
                ->with(['organizacion:id,nombre', 'cuenta:id,name,email', 'solicitante:id,name', 'resolutor:id,name'])
                ->orderByDesc('solicitada_en')
                ->limit(100)
                ->get()
                ->map(static fn (SolicitudPlataforma $solicitud): array => self::solicitud($solicitud, $yo, $resolver))
                ->values()
                ->all(),
        ]);
    }

    public function store(SolicitarRescateRequest $request, Organizacion $organizacion, SolicitarRescate $solicitar): RedirectResponse
    {
        /** @var User $yo */
        $yo = $request->user();
        $cuentaId = $request->validated('cuenta_id');

        $cuenta = $cuentaId === null ? null : User::query()
            ->where('organizacion_id', $organizacion->id)
            ->whereKey((int) $cuentaId)
            ->firstOrFail();

        try {
            $solicitar($yo, $organizacion, $request->tipo(), $cuenta, $request->datos(), (string) $request->validated('verificacion'));
        } catch (RescateNoPermitido $error) {
            return back()->withErrors(['rescate' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Solicitud registrada. La ejecuta otra persona de Administración desde Solicitudes.');

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    public function ejecutar(Request $request, SolicitudPlataforma $solicitud, ResolverRescate $resolver): RedirectResponse
    {
        /** @var User $yo */
        $yo = $request->user();

        try {
            $resolver->ejecutar($yo, $solicitud);
        } catch (RescateNoPermitido|OperacionDeCuentaNoPermitida $error) {
            return back()->withErrors(['solicitud' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Rescate ejecutado. Se ha avisado al cliente.');

        return to_route('plataforma.solicitudes.index');
    }

    public function rechazar(Request $request, SolicitudPlataforma $solicitud, ResolverRescate $resolver): RedirectResponse
    {
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:2000']])['motivo'];

        /** @var User $yo */
        $yo = $request->user();

        try {
            $resolver->rechazar($yo, $solicitud, (string) $motivo);
        } catch (RescateNoPermitido $error) {
            return back()->withErrors(['solicitud' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Solicitud rechazada.');

        return to_route('plataforma.solicitudes.index');
    }

    /**
     * @return array<string, mixed>
     */
    public static function solicitud(SolicitudPlataforma $solicitud, User $yo, ResolverRescate $resolver): array
    {
        $estado = $solicitud->estado();

        return [
            'id' => $solicitud->id,
            'tipo' => $solicitud->tipo->etiqueta(),
            'organizacion' => ['id' => $solicitud->organizacion_afectada_id, 'nombre' => $solicitud->organizacion->nombre],
            'cuenta' => $solicitud->cuenta === null
                ? (($solicitud->datos['nombre'] ?? '').' ('.($solicitud->datos['email'] ?? '').') — nueva')
                : "{$solicitud->cuenta->name} ({$solicitud->cuenta->email})",
            'verificacion' => $solicitud->verificacion,
            'solicitante' => $solicitud->solicitante?->name,
            'solicitadaEn' => $solicitud->solicitada_en->toIso8601String(),
            'resolutor' => $solicitud->resolutor?->name,
            'resueltaEn' => $solicitud->resuelta_en?->toIso8601String(),
            'motivoRechazo' => $solicitud->motivo_rechazo,
            'sinSegundaPersona' => $solicitud->sin_segunda_persona,
            'pedidaPorMi' => $solicitud->solicitada_por === $yo->id,
            'puedoEjecutar' => $resolver->puedeEjecutar($yo, $solicitud),
            'estado' => [
                'valor' => $estado->value,
                'etiqueta' => $estado->etiqueta(),
                'tono' => $estado->tono(),
                'icono' => $estado->icono(),
            ],
        ];
    }
}
