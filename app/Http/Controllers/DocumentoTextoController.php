<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Http\Requests\GuardarNarrativaDocumentoRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los textos de UN documento.
 *
 * Pantalla aparte y no una pestaña de la ficha: la ficha lleva un `usePoll` cada
 * tres segundos y un editor largo conviviendo con recargas parciales es pedir un
 * conflicto de estado sucio.
 */
class DocumentoTextoController extends Controller
{
    public function edit(
        Documento $documento,
        MaterializarSecciones $materializar,
        ResolverNarrativa $resolver,
    ): Response {
        // Idempotente: los documentos creados antes de que esto existiera no
        // tienen filas, y es aquí donde las estrenan.
        $materializar($documento);

        return Inertia::render('documentos/Textos', [
            'documento' => [
                'id' => $documento->id,
                'codigo' => $documento->codigo,
                'titulo' => $documento->titulo,
                'tipoEtiqueta' => $documento->tipo->etiqueta(),
            ],
            'secciones' => $this->secciones($documento, $resolver, app(MarkdownDocumento::class)),
        ]);
    }

    public function update(
        GuardarNarrativaDocumentoRequest $request,
        Documento $documento,
        GuardarNarrativa $guardar,
    ): RedirectResponse {
        $guardar($documento, $request->textos());

        Inertia::flash('exito', 'Textos guardados. Regenera el borrador para verlos en el PDF.');

        return to_route('documentos.show', $documento);
    }

    /**
     * Devuelve un hueco al texto de la plantilla.
     */
    public function restablecer(
        Documento $documento,
        string $seccion,
        GuardarNarrativa $guardar,
    ): RedirectResponse {
        $clave = SeccionNarrativa::tryFrom($seccion);

        abort_if($clave === null || ! $clave->aplicaA($documento->tipo), 404);

        $guardar->restablecer($documento, $clave);

        Inertia::flash('exito', "«{$clave->etiqueta()}» vuelve al texto de la plantilla.");

        return to_route('documentos.textos.edit', $documento);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function secciones(
        Documento $documento,
        ResolverNarrativa $resolver,
        MarkdownDocumento $markdown,
    ): array {
        $textos = $resolver->paraDocumento($documento->fresh() ?? $documento);
        $filas = $resolver->filasDe($documento);

        return array_map(
            function (SeccionNarrativa $seccion) use ($textos, $filas, $documento, $resolver, $markdown): array {
                $fila = $filas[$seccion->value] ?? null;

                return [
                    'clave' => $seccion->value,
                    'etiqueta' => $seccion->etiqueta(),
                    'ayuda' => $seccion->ayuda(),
                    'maximo' => $seccion->maxCaracteres(),
                    'contenido' => $textos[$seccion->value] ?? '',
                    // Lo que carga el editor: saneado, y SIN bajar los
                    // encabezados, que eso es maquetación del PDF.
                    'contenidoHtml' => $markdown->aHtmlDeEditor($textos[$seccion->value] ?? ''),
                    'origen' => $fila?->origen->value ?? 'plantilla',
                    'origenEtiqueta' => $fila?->origen->etiqueta() ?? 'De la plantilla',
                    'origenTono' => $fila?->origen->tono() ?? 'marco',
                    // Sólo se ofrece «Restablecer» cuando hay algo a lo que volver.
                    'restablecible' => $fila?->esPropio() ?? false,
                    'deLaPlantilla' => $resolver->desdeLaPlantilla($documento->tipo, $seccion),
                ];
            },
            SeccionNarrativa::paraTipo($documento->tipo),
        );
    }
}
