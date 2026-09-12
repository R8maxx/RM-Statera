<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Models\Documento;
use Illuminate\Support\Facades\View;

/**
 * El documento, como HTML listo para Gotenberg.
 *
 * Una sola entrada para los dos caminos que existen —la generación de verdad y
 * el `--html` del comando, que es con el que se trabaja el diseño—, porque
 * cuando eran dos llamadas a `View::make` separadas se arreglaba una y se
 * olvidaba la otra.
 */
final readonly class HtmlDocumento
{
    public function __construct(
        private ResolverCuerpo $resolver,
        private RenderizadorCuerpo $renderizador,
    ) {}

    public function __invoke(Documento $documento, ContenidoDocumento $contenido): string
    {
        return $this->conCuerpo($contenido, $this->cuerpo($documento, $contenido));
    }

    /**
     * El cuerpo que se va a imprimir, listo y con los bloques blindados al día.
     *
     * Se expone aparte porque `GenerarDocumento` lo necesita **dos veces**: para
     * pintarlo y para congelarlo en la instantánea de la versión. Resolverlo dos
     * veces sería arriesgarse a que el PDF y la instantánea no fueran del mismo
     * documento, que es exactamente lo que la instantánea existe para descartar.
     *
     * @return array<string, mixed>
     */
    public function cuerpo(Documento $documento, ContenidoDocumento $contenido): array
    {
        return $this->resolver->paraGenerar($documento, $contenido);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    public function conCuerpo(ContenidoDocumento $contenido, array $cuerpo): string
    {
        return View::make('documentos.layout', [
            'contenido' => $contenido,
            'cuerpo' => $this->renderizador->aHtml($cuerpo),
        ])->render();
    }
}
