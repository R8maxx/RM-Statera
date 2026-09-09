<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\RegistrarEvidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Requests\GuardarEvidenciaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\EvidenciaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function show(Evidencia $evidencia): Response
    {
        $evidencia->load([
            'responsable',
            'implantaciones.requisito.marco',
            'implantaciones.sistema',
        ]);

        return Inertia::render('evidencias/Ficha', [
            'evidencia' => $this->serializar($evidencia),
            'vinculos' => $evidencia->implantaciones
                ->map(fn (Implantacion $implantacion): array => [
                    'implantacionId' => $implantacion->id,
                    'codigo' => $implantacion->requisito->codigo,
                    'titulo' => $implantacion->requisito->titulo,
                    'marco' => $implantacion->requisito->marco?->codigo,
                    'sistema' => $implantacion->sistema->codigo,
                    'estado' => $implantacion->estado->value,
                    'estadoEtiqueta' => $implantacion->estado->etiqueta(),
                    'nota' => $implantacion->getRelationValue('pivot')?->getAttribute('nota'),
                ])
                ->all(),
            // Cuántos marcos distintos cubre. Es la cifra que justifica el
            // producto: registrar una vez y contar en todos.
            'marcos' => $evidencia->implantaciones
                ->map(fn (Implantacion $implantacion): ?string => $implantacion->requisito->marco?->codigo)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);
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
            'haCaducado' => $evidencia->haCaducado(),
            'responsable_id' => $evidencia->responsable_id,
            'responsable' => $evidencia->responsable?->name,
        ];
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
