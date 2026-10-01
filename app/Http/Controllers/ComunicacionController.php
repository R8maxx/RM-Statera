<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Enums\TipoRetroalimentacion;
use App\Domain\Comunicacion\Excepciones\ComunicacionInvalida;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Comunicacion\RegistrarComunicacion;
use App\Domain\Comunicacion\RegistroComunicacion;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Evidencia\Models\Evidencia;
use App\Http\Requests\GuardarComunicacionRequest;
use App\Http\Resources\ComunicacionRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que se comunicó y lo que se recibió: la mitad de la 7.4 que son hechos, y
 * la retroalimentación de la 9.3.2 e).
 *
 * Lo emitido se suele registrar desde la ficha de su línea del plan; aquí entra
 * lo emitido suelto —el aviso que no estaba previsto— y, sobre todo, **lo
 * recibido**, que no cumple ningún plan.
 */
class ComunicacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ComunicacionRecurso $recurso, RegistroComunicacion $registro): Response
    {
        return Inertia::render('comunicaciones/Index', [
            ...$this->tabla($recurso, $request),
            'pendientes' => [$registro->recibidasSinRespuesta()],
            'total' => Comunicacion::query()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        $sentido = SentidoComunicacion::tryFrom((string) $request->query('sentido')) ?? SentidoComunicacion::Recibida;

        return Inertia::render('comunicaciones/Formulario', [
            'comunicacion' => null,
            'sentido' => $sentido->value,
            'sugerencia' => ['fecha' => now()->toDateString()],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarComunicacionRequest $request, RegistrarComunicacion $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $sentido = SentidoComunicacion::from((string) $datos['sentido']);

        // Por el modelo y no por el id: así pasa por el scope de organización.
        $prevista = ($datos['comunicacion_prevista_id'] ?? null) === null
            ? null
            : ComunicacionPrevista::query()->findOrFail($datos['comunicacion_prevista_id']);

        try {
            $registrar(
                $sentido,
                Carbon::parse((string) $datos['fecha']),
                array_intersect_key($datos, array_flip([
                    'asunto', 'resumen', 'canal', 'parte_interesada_id', 'tipo_recibida', 'respuesta', 'evidencia_id',
                ])),
                $request->user(),
                $prevista,
            );
        } catch (ComunicacionInvalida $error) {
            return back()->withErrors(['fecha' => $error->getMessage()])->withInput();
        }

        Inertia::flash('exito', $sentido === SentidoComunicacion::Recibida ? 'Lo recibido queda registrado.' : 'Comunicación registrada.');

        return to_route('comunicaciones.index');
    }

    public function edit(Comunicacion $comunicacion): Response
    {
        $comunicacion->load('prevista');

        return Inertia::render('comunicaciones/Formulario', [
            'comunicacion' => [
                'id' => $comunicacion->id,
                'fecha' => $comunicacion->fecha->toDateString(),
                'prevista' => $comunicacion->prevista?->codigo,
                'asunto' => $comunicacion->asunto,
                'resumen' => $comunicacion->resumen,
                'canal' => $comunicacion->canal->value,
                'parte_interesada_id' => $comunicacion->parte_interesada_id === null ? null : (string) $comunicacion->parte_interesada_id,
                'tipo_recibida' => $comunicacion->tipo_recibida?->value,
                'respuesta' => $comunicacion->respuesta,
                'evidencia_id' => $comunicacion->evidencia_id === null ? null : (string) $comunicacion->evidencia_id,
            ],
            'sentido' => $comunicacion->sentido->value,
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarComunicacionRequest $request, Comunicacion $comunicacion): RedirectResponse
    {
        $datos = $request->editables();

        // El canal no puede quedarse vacío: la columna no es nula.
        if (($datos['canal'] ?? null) === null) {
            unset($datos['canal']);
        }

        $comunicacion->update($datos);

        Inertia::flash('exito', 'Comunicación actualizada.');

        return to_route('comunicaciones.index');
    }

    public function destroy(Comunicacion $comunicacion): RedirectResponse
    {
        $comunicacion->delete();

        Inertia::flash('exito', 'Comunicación eliminada.');

        return to_route('comunicaciones.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'canales' => array_map(
                static fn (CanalComunicacion $canal): array => ['valor' => $canal->value, 'etiqueta' => $canal->etiqueta()],
                CanalComunicacion::cases(),
            ),
            'tipos' => array_map(
                static fn (TipoRetroalimentacion $tipo): array => ['valor' => $tipo->value, 'etiqueta' => $tipo->etiqueta()],
                TipoRetroalimentacion::cases(),
            ),
            'partesInteresadas' => ParteInteresada::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(static fn (ParteInteresada $parte): array => ['valor' => (string) $parte->id, 'etiqueta' => $parte->nombre])
                ->all(),
            'previstas' => ComunicacionPrevista::query()
                ->activas()
                ->orderBy('codigo')
                ->get()
                ->map(static fn (ComunicacionPrevista $prevista): array => [
                    'valor' => (string) $prevista->id,
                    'etiqueta' => "{$prevista->codigo} — {$prevista->titulo}",
                ])
                ->all(),
            'evidencias' => Evidencia::query()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(static fn (Evidencia $evidencia): array => ['valor' => (string) $evidencia->id, 'etiqueta' => $evidencia->titulo])
                ->all(),
        ];
    }
}
