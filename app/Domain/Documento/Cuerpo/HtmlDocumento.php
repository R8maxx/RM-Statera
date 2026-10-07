<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\IndiceDocumento;
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
        private IndiceDocumento $indice,
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
     * El documento entero en una sola página HTML, portada incluida.
     *
     * Es lo que vuelca `documentos:generar --html` para mirarlo en un navegador.
     * Sin índice: sus números sólo existen después de imprimir.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    public function conCuerpo(ContenidoDocumento $contenido, array $cuerpo): string
    {
        return $this->pagina($contenido, $this->renderizador->aHtml($cuerpo));
    }

    /**
     * Las dos impresiones: la portada, a sangre, y el resto con su índice.
     *
     * `$entradas` a `null` imprime sin índice. Con entradas y sin páginas sale el
     * índice con números de relleno y un marcador por sección: es la pasada de
     * medida, y tiene que paginar exactamente igual que la definitiva.
     *
     * @param  array<string, mixed>  $cuerpo
     * @param  list<array{id: string, titulo: string}>|null  $entradas
     * @param  array<string, int>|null  $paginas
     * @return array{portada: string, cuerpo: string}
     */
    public function paraImprimir(ContenidoDocumento $contenido, array $cuerpo, ?array $entradas = null, ?array $paginas = null): array
    {
        $medida = $entradas !== null && $paginas === null;
        $partes = $this->renderizador->partes($cuerpo, $medida);
        $indice = $entradas === null ? '' : $this->indice->html($entradas, $paginas);

        return [
            'portada' => $this->pagina($contenido, $partes['portada']),
            'cuerpo' => $this->pagina($contenido, $indice.$partes['cuerpo']),
        ];
    }

    private function pagina(ContenidoDocumento $contenido, string $html): string
    {
        return View::make('documentos.layout', [
            'contenido' => $contenido,
            'cuerpo' => $html,
        ])->render();
    }
}
