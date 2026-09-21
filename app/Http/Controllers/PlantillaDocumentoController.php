<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\PlantillaSeccion;
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
    /**
     * Las ocho plantillas, con sus textos dentro.
     *
     * **Los textos viajan al cliente porque la búsqueda entra en ellos**, que es
     * lo que de verdad falta aquí: son cincuenta y seis huecos repartidos por
     * ocho pantallas y no había forma de encontrar dónde se escribió una frase.
     * Buscar sólo sobre ocho títulos no vale el control que ocupa.
     *
     * El corpus de fábrica **mide 10,9 kB** —medido, no estimado: cincuenta y
     * seis huecos, el mayor de 671 caracteres—, así que se manda entero y se
     * filtra en cliente, como en la ficha de una acción formativa. El tope
     * teórico son 56 × 20.000 caracteres; el día que una organización se acerque,
     * esto se convierte en una búsqueda de servidor.
     *
     * Tres consultas y no dieciséis: una por los textos, una por el recuento de
     * documentos y una por quién tocó cada plantilla la última vez.
     */
    public function index(ResolverNarrativa $resolver): Response
    {
        $plantillas = $resolver->todasLasPlantillas();
        $documentos = $this->documentosPorTipo();
        $retoques = $this->ultimoRetoquePorTipo();

        return Inertia::render('plantillas/Index', [
            'tipos' => array_map(
                function (TipoDocumento $tipo) use ($plantillas, $documentos, $retoques): array {
                    $secciones = array_map(
                        function (SeccionNarrativa $seccion) use ($tipo, $plantillas): array {
                            $actual = $plantillas[$tipo->value][$seccion->value] ?? '';

                            return [
                                'clave' => $seccion->value,
                                'etiqueta' => $seccion->etiqueta(),
                                'contenido' => $actual,
                                // «Personalizado» es «distinto del que trae
                                // Statera», igual que en el editor.
                                'personalizada' => $actual !== TextosDeFabrica::para($tipo, $seccion),
                            ];
                        },
                        SeccionNarrativa::paraTipo($tipo),
                    );

                    return [
                        'valor' => $tipo->value,
                        'etiqueta' => $tipo->etiqueta(),
                        // Una declaración se calcula y una política se redacta:
                        // son dos géneros distintos y la pantalla los mezclaba.
                        'familia' => $tipo->esRedactado() ? 'redactado' : 'calculado',
                        'secciones' => $secciones,
                        'personalizadas' => count(array_filter($secciones, fn (array $s): bool => $s['personalizada'])),
                        'documentos' => $documentos[$tipo->value] ?? 0,
                        'retoque' => $retoques[$tipo->value] ?? null,
                    ];
                },
                TipoDocumento::cases(),
            ),
        ]);
    }

    /**
     * Cuántos documentos hay de cada tipo, en una consulta.
     *
     * @return array<string, int>
     */
    private function documentosPorTipo(): array
    {
        /** @var array<string, int> $recuento */
        $recuento = Documento::query()
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return $recuento;
    }

    /**
     * Cuándo se tocó por última vez cada plantilla y quién la tocó.
     *
     * El modelo lo guarda desde el principio y la pantalla no lo enseñaba, que en
     * una herramienta de cumplimiento es justo el dato que se pregunta.
     *
     * `User` **no lleva el scope de organización** (la autenticación tiene que
     * poder encontrar a alguien antes de saber de qué organización es), así que
     * un `User::query()` suelto listaría usuarios de todos los clientes. Aquí no
     * hace falta acotar a mano porque los ids salen de filas que **sí** están
     * acotadas por las tres capas.
     *
     * @return array<string, array{en: string, por: ?string}>
     */
    private function ultimoRetoquePorTipo(): array
    {
        $filas = PlantillaSeccion::query()
            ->with('actualizadoPor:id,name')
            ->orderByDesc('updated_at')
            ->get();

        $retoques = [];

        foreach ($filas as $fila) {
            // Ordenadas de más reciente a más antigua: la primera de cada tipo es
            // la que manda.
            $retoques[$fila->tipo->value] ??= [
                'en' => (string) $fila->updated_at?->toIso8601String(),
                'por' => $fila->actualizadoPor?->name,
            ];
        }

        return $retoques;
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
}
