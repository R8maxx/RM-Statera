<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\GuardarPlantilla;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Documento\Narrativa\TextosDeFabrica;
use App\Http\Requests\GuardarPlantillaNarrativaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los textos base de la organización.
 *
 * Lo que se escribe aquí es el punto de partida de **los documentos que se creen
 * a partir de ahora**. Los que ya existen conservan el suyo, y la pantalla lo
 * dice: sin ese aviso, cualquiera daría por hecho que acaba de cambiar su SoA.
 */
class PlantillaDocumentoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('plantillas/Index', [
            'tipos' => array_map(
                fn (TipoDocumento $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'corta' => $tipo->etiquetaCorta(),
                    'secciones' => count(SeccionNarrativa::paraTipo($tipo)),
                    'personalizadas' => $this->personalizadas($tipo),
                    'documentos' => Documento::query()->where('tipo', $tipo->value)->count(),
                ],
                TipoDocumento::cases(),
            ),
        ]);
    }

    public function edit(
        TipoDocumento $tipo,
        ResolverNarrativa $resolver,
        MarkdownDocumento $markdown,
    ): Response {
        $textos = $resolver->paraPlantilla($tipo);

        return Inertia::render('plantillas/Editar', [
            'tipo' => [
                'valor' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'corta' => $tipo->etiquetaCorta(),
            ],
            'documentosAfectados' => Documento::query()->where('tipo', $tipo->value)->count(),
            'secciones' => array_map(
                function (SeccionNarrativa $seccion) use ($tipo, $textos, $markdown): array {
                    $deFabrica = TextosDeFabrica::para($tipo, $seccion);
                    $actual = $textos[$seccion->value] ?? '';

                    return [
                        'clave' => $seccion->value,
                        'etiqueta' => $seccion->etiqueta(),
                        'ayuda' => $seccion->ayuda(),
                        'maximo' => $seccion->maxCaracteres(),
                        'contenido' => $actual,
                        'contenidoHtml' => $markdown->aHtmlDeEditor($actual),
                        // Aquí «personalizado» es «distinto del que trae Statera».
                        'origen' => $actual === $deFabrica ? 'plantilla' : 'propio',
                        'origenEtiqueta' => $actual === $deFabrica ? 'De fábrica' : 'Personalizado',
                        'origenTono' => $actual === $deFabrica ? 'marco' : 'exigible',
                        'restablecible' => $actual !== $deFabrica,
                        'deLaPlantilla' => $deFabrica,
                    ];
                },
                SeccionNarrativa::paraTipo($tipo),
            ),
        ]);
    }

    public function update(
        GuardarPlantillaNarrativaRequest $request,
        TipoDocumento $tipo,
        GuardarPlantilla $guardar,
    ): RedirectResponse {
        $usuario = $request->user();

        $guardar($tipo, $request->textos(), $usuario instanceof User ? $usuario : null);

        Inertia::flash('exito', 'Plantilla guardada. Los documentos que ya existen conservan sus textos.');

        return to_route('plantillas.edit', $tipo->value);
    }

    public function restablecer(
        TipoDocumento $tipo,
        string $seccion,
        GuardarPlantilla $guardar,
    ): RedirectResponse {
        $clave = SeccionNarrativa::tryFrom($seccion);

        abort_if($clave === null || ! $clave->aplicaA($tipo), 404);

        $guardar->restablecer($tipo, $clave);

        Inertia::flash('exito', "«{$clave->etiqueta()}» vuelve al texto que trae Statera.");

        return to_route('plantillas.edit', $tipo->value);
    }

    /** Cuántos huecos ha tocado la organización respecto a lo que trae Statera. */
    private function personalizadas(TipoDocumento $tipo): int
    {
        $resolver = app(ResolverNarrativa::class);
        $textos = $resolver->paraPlantilla($tipo);

        $distintos = 0;

        foreach (SeccionNarrativa::paraTipo($tipo) as $seccion) {
            if (($textos[$seccion->value] ?? '') !== TextosDeFabrica::para($tipo, $seccion)) {
                $distintos++;
            }
        }

        return $distintos;
    }
}
