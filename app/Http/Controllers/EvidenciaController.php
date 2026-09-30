<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\EscrituraPropia;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\RegistrarEvidencia;
use App\Domain\Evidencia\Vigencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\GuardarEvidenciaRequest;
use App\Http\Requests\VincularRequisitosRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\EvidenciaRecurso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * El repositorio de pruebas.
 *
 * Delgado como el resto: valida con el `FormRequest`, delega en el dominio y
 * devuelve Inertia. El aislamiento lo pone el modelo, no este controlador.
 */
class EvidenciaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request): Response
    {
        return Inertia::render('evidencias/Index', $this->tabla(new EvidenciaRecurso, $request));
    }

    public function create(): Response
    {
        return Inertia::render('evidencias/Formulario', [
            'evidencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarEvidenciaRequest $request, RegistrarEvidencia $registrar): RedirectResponse
    {
        $evidencia = $registrar->crear($request->safe()->except('fichero'), $request->file('fichero'));

        Inertia::flash('exito', "Evidencia «{$evidencia->titulo}» registrada. Vincúlala a los requisitos que prueba.");

        return to_route('evidencias.show', $evidencia);
    }

    /**
     * La ficha: qué prueba esta evidencia, en cuántos marcos y hasta cuándo.
     */
    public function show(Request $request, Evidencia $evidencia, EscrituraPropia $escritura): Response
    {
        $evidencia->load([
            'responsable',
            'renovadaPor',
            'renuevaA',
            'implantaciones.requisito.marco',
            'implantaciones.sistema',
        ]);

        /** @var User $usuario */
        $usuario = $request->user();
        $vincula = $usuario->can(Permiso::ImplantacionesGestionar->value);

        $vinculos = $evidencia->implantaciones
            ->sortBy([
                fn (Implantacion $implantacion): string => (string) $implantacion->requisito->marco?->codigo,
                fn (Implantacion $implantacion): string => $implantacion->requisito->codigo,
            ], SORT_NATURAL)
            ->values();

        return Inertia::render('evidencias/Ficha', [
            'evidencia' => $this->serializar($evidencia),
            'vigencia' => Vigencia::de($evidencia)->toArray(),
            'renovadaPor' => $this->referencia($evidencia->renovadaPor),
            'renuevaA' => $this->referencia($evidencia->renuevaA),
            'vinculos' => $vinculos
                ->map(fn (Implantacion $implantacion): array => [
                    'implantacionId' => $implantacion->id,
                    'codigo' => $implantacion->requisito->codigo,
                    'titulo' => $implantacion->requisito->titulo,
                    'marco' => $implantacion->requisito->marco?->codigo,
                    'sistema' => $implantacion->sistema->codigo,
                    'estado' => $implantacion->estado->value,
                    'estadoEtiqueta' => $implantacion->estado->etiqueta(),
                    'estadoIcono' => $implantacion->estado->icono(),
                    'nota' => $implantacion->getRelationValue('pivot')?->getAttribute('nota'),
                    // `EscribeLoSuyo` rechazaría el «Quitar» de una ajena: no
                    // se ofrece (§ 4.19).
                    'editable' => $vincula && $escritura->puedeEscribir($usuario, $implantacion),
                ])
                ->all(),
            'marcos' => $this->marcos($evidencia),
            'puede' => [
                'gestionar' => $usuario->can(Permiso::EvidenciasGestionar->value),
                'vincular' => $vincula,
            ],
            // Se pide al abrir el diálogo: con una recarga parcial, igual que
            // las candidatas de la ficha del requisito.
            'implantacionesDisponibles' => Inertia::optional(fn (): array => $vincula
                ? $this->disponibles($evidencia, $usuario, $escritura)
                : []),
        ]);
    }

    /**
     * La une con varios requisitos de golpe, desde su ficha.
     *
     * Las que el técnico no puede escribir (§ 4.19) se saltan y se cuentan, como
     * en el cambio de estado en bloque: no hay implantación en la ruta, así que
     * `EscribeLoSuyo` no puede decidir por él.
     */
    public function vincularRequisitos(
        VincularRequisitosRequest $request,
        Evidencia $evidencia,
        VincularEvidencia $vincular,
        EscrituraPropia $escritura,
    ): RedirectResponse {
        /** @var User $usuario */
        $usuario = $request->user();
        $nota = $request->string('nota')->toString() ?: null;

        $implantaciones = Implantacion::query()
            ->whereIn('id', $request->collect('implantaciones')->map(fn (mixed $id): int => (int) $id)->all())
            ->get();

        $ajenas = 0;

        foreach ($implantaciones as $implantacion) {
            if (! $escritura->puedeEscribir($usuario, $implantacion)) {
                $ajenas++;

                continue;
            }

            $vincular->vincular($evidencia, $implantacion, $usuario, $nota);
        }

        $hechas = $implantaciones->count() - $ajenas;

        Inertia::flash('exito', match (true) {
            $ajenas === 0 => $hechas === 1
                ? "«{$evidencia->titulo}» ya prueba 1 requisito más."
                : "«{$evidencia->titulo}» ya prueba {$hechas} requisitos más.",
            default => "Vinculada a {$hechas}. {$ajenas} están a cargo de otra persona y no se han tocado.",
        });

        return back();
    }

    /**
     * El formulario de alta, relleno con lo que no cambia al renovar.
     *
     * Fechas y prueba se piden de nuevo: la renovación es otra obtención, con su
     * fichero o su enlace, y la caducidad se vuelve a derivar de la periodicidad.
     */
    public function renovar(Evidencia $evidencia): Response|RedirectResponse
    {
        if ($evidencia->estaRenovada()) {
            return $this->yaRenovada($evidencia);
        }

        $evidencia->loadCount('implantaciones');

        return Inertia::render('evidencias/Formulario', [
            'evidencia' => null,
            'renueva' => [
                'id' => $evidencia->id,
                'titulo' => $evidencia->titulo,
                'requisitos' => (int) $evidencia->getAttribute('implantaciones_count'),
                'plantilla' => [
                    ...$this->serializar($evidencia),
                    'fecha_obtencion' => Carbon::today()->toDateString(),
                    'fecha_caducidad' => null,
                ],
            ],
            ...$this->opciones(),
        ]);
    }

    public function guardarRenovacion(
        GuardarEvidenciaRequest $request,
        Evidencia $evidencia,
        RegistrarEvidencia $registrar,
    ): RedirectResponse {
        if ($evidencia->estaRenovada()) {
            return $this->yaRenovada($evidencia);
        }

        $nueva = $registrar->renovar(
            $evidencia,
            $request->safe()->except('fichero'),
            $request->file('fichero'),
            $request->user(),
        );

        Inertia::flash('exito', "Evidencia «{$nueva->titulo}» renovada. Prueba lo mismo que la anterior, que deja de avisar.");

        return to_route('evidencias.show', $nueva);
    }

    public function edit(Evidencia $evidencia): Response
    {
        return Inertia::render('evidencias/Formulario', [
            'evidencia' => $this->serializar($evidencia),
            ...$this->opciones(),
        ]);
    }

    public function update(
        GuardarEvidenciaRequest $request,
        Evidencia $evidencia,
        RegistrarEvidencia $registrar,
    ): RedirectResponse {
        $registrar->actualizar($evidencia, $request->validated());

        Inertia::flash('exito', "Evidencia «{$evidencia->titulo}» actualizada.");

        return to_route('evidencias.show', $evidencia);
    }

    public function destroy(Evidencia $evidencia): RedirectResponse
    {
        $titulo = $evidencia->titulo;
        $probaba = $evidencia->implantaciones()->count();

        // El fichero no se borra del almacén: con Object Lock no se puede, y no
        // se debe. La traza registra la baja de la fila.
        $evidencia->delete();

        Inertia::flash('exito', $probaba === 0
            ? "Evidencia «{$titulo}» eliminada."
            : "Evidencia «{$titulo}» eliminada. {$probaba} requisitos se quedan sin esa prueba.");

        return to_route('evidencias.index');
    }

    /**
     * Descarga por URL firmada de corta duración.
     *
     * Nunca un enlace directo al bucket: es privado, y el acceso tiene que pasar
     * por el scope de organización —esta ruta— para que la evidencia de un
     * cliente no sea legible con sólo saber su ruta.
     */
    public function descargar(Evidencia $evidencia): SymfonyRedirect
    {
        abort_unless($evidencia->esFichero(), 404);

        return redirect()->away(
            Storage::disk((string) $evidencia->disco)->temporaryUrl(
                (string) $evidencia->ruta,
                now()->addMinutes(5),
                ['ResponseContentDisposition' => 'attachment; filename="'.$evidencia->nombre_fichero.'"'],
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Evidencia $evidencia): array
    {
        return [
            'id' => $evidencia->id,
            'titulo' => $evidencia->titulo,
            'tipo' => $evidencia->tipo->value,
            'tipoEtiqueta' => $evidencia->tipo->etiqueta(),
            'descripcion' => $evidencia->descripcion,
            'esFichero' => $evidencia->esFichero(),
            'nombre_fichero' => $evidencia->nombre_fichero,
            'mime' => $evidencia->mime,
            'tamano' => $evidencia->tamano,
            // La huella entera, no un prefijo: es lo que se contrasta con el
            // fichero que se le entrega al auditor.
            'hash_sha256' => $evidencia->hash_sha256,
            'url_externa' => $evidencia->url_externa,
            'fecha_obtencion' => $evidencia->fecha_obtencion->toDateString(),
            'fecha_caducidad' => $evidencia->fecha_caducidad?->toDateString(),
            'periodicidad_renovacion' => $evidencia->periodicidad_renovacion?->value,
            'periodicidadEtiqueta' => $evidencia->periodicidad_renovacion?->etiqueta(),
            'haCaducado' => $evidencia->haCaducado(),
            'responsable_id' => $evidencia->responsable_id,
            'responsable' => $evidencia->responsable?->name,
            'renovada_por_id' => $evidencia->renovada_por_id,
        ];
    }

    private function yaRenovada(Evidencia $evidencia): RedirectResponse
    {
        Inertia::flash('error', "«{$evidencia->titulo}» ya se renovó. La vigente es la que la sustituye.");

        return to_route('evidencias.show', (int) $evidencia->renovada_por_id);
    }

    /**
     * @return ?array{id: int, titulo: string, fecha_obtencion: string}
     */
    private function referencia(?Evidencia $evidencia): ?array
    {
        return $evidencia === null ? null : [
            'id' => $evidencia->id,
            'titulo' => $evidencia->titulo,
            'fecha_obtencion' => $evidencia->fecha_obtencion->toDateString(),
        ];
    }

    /**
     * Los marcos que la organización tiene implantados, con cuántos requisitos
     * prueba esta evidencia en cada uno.
     *
     * Con los que suman cero: «no prueba nada de la ISO» es un dato, y es
     * justo el que dice dónde más podría contar. Cuántos marcos cubre es la
     * cifra que justifica el producto: registrar una vez y contar en todos.
     *
     * @return list<array{codigo: string, nombre: string, requisitos: int}>
     */
    private function marcos(Evidencia $evidencia): array
    {
        $cuenta = $evidencia->implantaciones
            ->countBy(fn (Implantacion $implantacion): string => (string) $implantacion->requisito->marco?->codigo);

        // Por las implantaciones y no desde el catálogo: el catálogo es global y
        // no sabe qué marcos tiene cada organización (invariante 2).
        $implantados = Implantacion::query()
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->distinct()
            ->pluck('requisitos.marco_id');

        return Marco::query()
            ->whereIn('id', $implantados)
            ->orderBy('codigo')
            ->get()
            ->map(fn (Marco $marco): array => [
                'codigo' => $marco->codigo,
                'nombre' => $marco->nombre,
                'requisitos' => (int) ($cuenta[$marco->codigo] ?? 0),
            ])
            ->sortByDesc('requisitos')
            ->values()
            ->all();
    }

    /**
     * Las implantaciones a las que todavía se puede vincular, y que quien mira
     * puede escribir.
     *
     * @return list<array{valor: string, etiqueta: string, descripcion: string}>
     */
    private function disponibles(Evidencia $evidencia, User $usuario, EscrituraPropia $escritura): array
    {
        return Implantacion::query()
            ->with(['requisito.marco', 'sistema'])
            ->whereDoesntHave('evidencias', fn (Builder $consulta) => $consulta->where('evidencias.id', $evidencia->id))
            ->get()
            ->filter(fn (Implantacion $implantacion): bool => $escritura->puedeEscribir($usuario, $implantacion))
            ->sortBy([
                fn (Implantacion $implantacion): string => (string) $implantacion->requisito->marco?->codigo,
                fn (Implantacion $implantacion): string => $implantacion->requisito->codigo,
            ], SORT_NATURAL)
            ->map(fn (Implantacion $implantacion): array => [
                'valor' => (string) $implantacion->id,
                'etiqueta' => "{$implantacion->requisito->codigo} · {$implantacion->requisito->titulo}",
                'descripcion' => "{$implantacion->requisito->marco->codigo} · {$implantacion->sistema->codigo}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tipos' => array_map(
                static fn (TipoEvidencia $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                ],
                TipoEvidencia::cases(),
            ),
            'periodicidades' => array_map(
                static fn (PeriodicidadRenovacion $periodicidad): array => [
                    'valor' => $periodicidad->value,
                    'etiqueta' => $periodicidad->etiqueta(),
                ],
                PeriodicidadRenovacion::cases(),
            ),
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
        ];
    }
}
