<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Persona\CodigoAccionFormativa;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistrarAsistencia;
use App\Http\Requests\GuardarAccionFormativaRequest;
use App\Http\Requests\RegistrarAsistenciaRequest;
use App\Http\Resources\AccionFormativaRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La formación y la concienciación: `mp.per.3` y `mp.per.4`.
 *
 * **Pantalla propia y no un bloque de la ficha de una persona**, por el mismo
 * motivo que la checklist de una auditoría: lo que se registra es una **sesión**
 * con veinte convocados, y marcar veinte asistencias exige marcado en bloque. Al
 * revés —apuntar sesión por sesión desde cada ficha— son veinte peticiones y
 * veinte oportunidades de dejarlo a medias.
 *
 * `/formacion` es la lista de sesiones y `/formacion/{accion}` es la convocatoria
 * de una, con su registro de asistencia.
 */
class FormacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, AccionFormativaRecurso $recurso): Response
    {
        return Inertia::render('formacion/Index', [
            ...$this->tabla($recurso, $request),
            'total' => AccionFormativa::query()->count(),
            'sinAsistencia' => AccionFormativa::query()->sinAsistencia()->count(),
        ]);
    }

    public function create(CodigoAccionFormativa $codigos): Response
    {
        return Inertia::render('formacion/Formulario', [
            'accion' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'fecha' => now()->toDateString(),
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarAccionFormativaRequest $request): RedirectResponse
    {
        $accion = AccionFormativa::query()->create($request->validated());

        Inertia::flash('exito', "Sesión {$accion->codigo} registrada. Ahora se convoca.");

        return to_route('formacion.show', $accion);
    }

    /**
     * La convocatoria de una sesión.
     *
     * Llegan **todas** las personas activas, no sólo las convocadas: convocar es
     * justamente elegir de esa lista, y con una lista corta hay que ir a otra
     * pantalla a buscar a quien falta. Las dadas de baja que ya estaban
     * convocadas siguen apareciendo —asistieron de verdad y borrarlas reescribiría
     * el registro—, pero no se ofrecen para convocar.
     */
    public function show(AccionFormativa $accion): Response
    {
        $accion->load(['asistencias.persona', 'evidencia']);

        $convocadas = $accion->asistencias->keyBy('persona_id');

        $personas = Persona::query()
            ->activas()
            ->orderBy('nombre')
            ->get()
            ->concat(
                $accion->asistencias
                    ->map(static fn ($asistencia): ?Persona => $asistencia->persona)
                    ->filter(static fn (?Persona $persona): bool => $persona !== null && ! $persona->estaActiva()),
            )
            ->unique('id')
            ->sortBy('nombre')
            ->values();

        return Inertia::render('formacion/Ficha', [
            'accion' => $this->serializar($accion),
            'personas' => $personas
                ->map(static fn (Persona $persona): array => [
                    'id' => $persona->id,
                    'codigo' => $persona->codigo,
                    'nombre' => $persona->nombre,
                    'puesto' => $persona->puesto,
                    'activa' => $persona->estaActiva(),
                    'convocada' => $convocadas->has($persona->id),
                    'asistio' => (bool) $convocadas->get($persona->id)?->asistio,
                ])
                ->all(),
            'puedeGestionar' => request()->user()?->can(Permiso::PersonasGestionar->value) ?? false,
        ]);
    }

    public function edit(AccionFormativa $accion): Response
    {
        return Inertia::render('formacion/Formulario', [
            'accion' => $this->serializar($accion),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarAccionFormativaRequest $request, AccionFormativa $accion): RedirectResponse
    {
        $accion->update($request->validated());

        Inertia::flash('exito', 'Sesión actualizada.');

        return to_route('formacion.show', $accion);
    }

    public function destroy(AccionFormativa $accion): RedirectResponse
    {
        $codigo = $accion->codigo;
        $accion->delete();

        Inertia::flash('exito', "Sesión {$codigo} eliminada.");

        return to_route('formacion.index');
    }

    /**
     * La convocatoria entera, en una sola escritura.
     *
     * Quien sale de la lista deja de estar convocado, que no es lo mismo que haber
     * faltado. Ver `RegistrarAsistencia`.
     */
    public function registrarAsistencia(
        RegistrarAsistenciaRequest $request,
        AccionFormativa $accion,
        RegistrarAsistencia $registrar,
    ): RedirectResponse {
        $registrar($accion, $request->convocadas());

        Inertia::flash('exito', 'Asistencia registrada.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(AccionFormativa $accion): array
    {
        $convocadas = $accion->relationLoaded('asistencias') ? $accion->asistencias : collect();

        return [
            'id' => $accion->id,
            'codigo' => $accion->codigo,
            'titulo' => $accion->titulo,
            'tipo' => $accion->tipo->value,
            'tipoEtiqueta' => $accion->tipo->etiqueta(),
            'tipoTono' => $accion->tipo->tono(),
            'tipoIcono' => $accion->tipo->icono(),
            'medida' => $accion->tipo->medida(),
            'fecha' => $accion->fecha->toDateString(),
            'fechaEtiqueta' => $accion->fecha->format('d/m/Y'),
            'duracion_horas' => $accion->duracion_horas,
            'contenido' => $accion->contenido,
            'evidencia_id' => $accion->evidencia_id,
            'evidencia' => $accion->relationLoaded('evidencia') ? $accion->evidencia?->titulo : null,
            'convocadas' => $convocadas->count(),
            'asistentes' => $convocadas->where('asistio', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tipos' => array_map(
                static fn (TipoAccionFormativa $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'medida' => $tipo->medida(),
                ],
                TipoAccionFormativa::cases(),
            ),
        ];
    }
}
