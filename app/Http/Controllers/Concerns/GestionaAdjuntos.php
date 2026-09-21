<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Adjunto\BorrarAdjunto;
use App\Domain\Adjunto\Concerns\ConAdjuntos;
use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Adjunto\SubirAdjunto;
use App\Http\Requests\SubirAdjuntoRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Subir, descargar y borrar los documentos de un registro.
 *
 * Va en un trait de controlador y no en un `AdjuntoController` propio porque las
 * rutas **cuelgan del anfitrión y heredan su permiso**: `personas.gestionar`
 * para los de una persona, el mismo para los de una sesión de formación. Un
 * controlador suelto necesitaría su propio verbo, y un adjunto no es un módulo,
 * es una capacidad que se le añade a uno.
 */
trait GestionaAdjuntos
{
    protected function subirAdjuntoDe(
        SubirAdjuntoRequest $request,
        Model&ConAdjuntos $anfitrion,
        SubirAdjunto $subir,
        string $ruta,
    ): RedirectResponse {
        $adjunto = $subir(
            $anfitrion,
            $request->file('fichero'),
            $request->string('titulo')->trim()->value(),
            $request->string('nota')->value() ?: null,
            $request->user(),
        );

        Inertia::flash('exito', "«{$adjunto->titulo}» está guardado.");

        return to_route($ruta, $anfitrion);
    }

    /**
     * La descarga es un **redirect a URL firmada de cinco minutos**, igual que
     * la de una evidencia: nunca un enlace al bucket, que es privado, ni un
     * `streamDownload` que haría pasar el fichero entero por PHP.
     */
    protected function descargarAdjuntoDe(Adjunto $adjunto): RedirectResponse
    {
        return redirect()->away(
            Storage::disk($adjunto->disco)->temporaryUrl(
                $adjunto->ruta,
                now()->addMinutes(5),
                ['ResponseContentDisposition' => sprintf('attachment; filename="%s"', $adjunto->nombre_fichero)],
            ),
        );
    }

    /**
     * Borrar se lleva **también el fichero del almacén**, a diferencia de una
     * evidencia. El motivo está en `BorrarAdjunto`: aquí puede haber datos
     * personales y conservar lo que alguien borró sería el fallo.
     */
    protected function borrarAdjuntoDe(
        Adjunto $adjunto,
        BorrarAdjunto $borrar,
        string $ruta,
        Model $anfitrion,
    ): RedirectResponse {
        $titulo = $adjunto->titulo;

        $borrar($adjunto);

        Inertia::flash('exito', "«{$titulo}» ya no está.");

        return to_route($ruta, $anfitrion);
    }

    /**
     * Los adjuntos de un registro, listos para la pantalla.
     *
     * @return list<array<string, mixed>>
     */
    protected function serializarAdjuntos(Model&ConAdjuntos $anfitrion, Request $request, string $base): array
    {
        return $anfitrion->adjuntosCargados()
            ->map(fn (Adjunto $adjunto): array => [
                'id' => $adjunto->id,
                'titulo' => $adjunto->titulo,
                'nota' => $adjunto->nota,
                'nombre_fichero' => $adjunto->nombre_fichero,
                'mime' => $adjunto->mime,
                'tamano' => $adjunto->tamano,
                'subidoPor' => $adjunto->subidoPor?->name,
                'fecha' => $adjunto->created_at?->format('d/m/Y'),
                'url' => "{$base}/{$adjunto->id}",
            ])
            ->values()
            ->all();
    }
}
