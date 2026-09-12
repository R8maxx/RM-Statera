<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Render\GraficaSvg;
use App\Http\Resources\Panel\SegmentoEstado;

/**
 * El cuerpo del documento, convertido al HTML que Gotenberg imprime.
 *
 * **Es la única frontera de saneado del documento, y funciona por omisión:** un
 * nodo que no esté en `EsquemaCuerpo` no se pinta, un atributo que no esté
 * declarado no sale, una clase que no esté enumerada no existe y un `href` que
 * no sea `http`, `https` o `mailto` se queda sin enlace. No hay ninguna rama que
 * diga «y si no, píntalo tal cual»: eso es lo que convierte un editor en una
 * inyección.
 *
 * El texto se escapa con `e()` sin excepción. En este árbol no hay ningún nodo
 * de HTML crudo, así que no existe el caso «esto ya viene saneado»: lo único que
 * sale sin escapar es el SVG de la gráfica, y ese **no viaja en el cuerpo** —el
 * nodo guarda los datos del reparto y el dibujo lo hace `GraficaSvg` aquí—.
 *
 * Los encabezados ya no se desplazan. Con el cuerpo entero editable, la
 * jerarquía que pide PDF/UA la garantiza el esquema: el `h1` es el título de la
 * portada y los niveles del editor salen tal cual.
 */
final class RenderizadorCuerpo
{
    public function __construct(private readonly GraficaSvg $graficas = new GraficaSvg) {}

    /**
     * @param  array<string, mixed>  $cuerpo  documento de ProseMirror
     */
    public function aHtml(array $cuerpo): string
    {
        return $this->nodo($cuerpo);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function nodo(array $nodo): string
    {
        $tipo = $nodo['type'] ?? null;

        if (! is_string($tipo) || ! EsquemaCuerpo::admite($tipo)) {
            return '';
        }

        if ($tipo === 'text') {
            return $this->textoConMarcas($nodo);
        }

        return match ($tipo) {
            'doc', 'grupo' => $this->hijos($nodo),

            'portada' => $this->envolver('section', $this->hijos($nodo), ['class' => 'portada']),
            'seccion' => $this->envolver('section', $this->hijos($nodo), ['class' => 'seccion']),

            'fileteMarca' => '<div class="portada__filete"></div>',
            'marcaPortada' => $this->envolver('div', $this->hijos($nodo), ['class' => 'portada__marca']),
            'pieDePortada' => $this->envolver('div', $this->hijos($nodo), ['class' => 'portada__pie pequeno suave']),

            'caja' => $this->caja($nodo),
            'ficha' => $this->envolver('div', $this->hijos($nodo), ['class' => 'ficha']),
            'fichaFila' => $this->fichaFila($nodo),
            'cifras' => $this->envolver('div', $this->hijos($nodo), ['class' => 'cifras']),
            'cifraDato' => $this->cifraDato($nodo),
            'grafica' => $this->grafica($nodo),
            'leyenda' => $this->envolver('div', $this->hijos($nodo), ['class' => 'leyenda']),
            'limitaciones' => $this->envolver('div', $this->hijos($nodo), ['class' => 'limitaciones']),
            'nota' => $this->envolver('div', $this->hijos($nodo), ['class' => 'pequeno suave']),
            'badge' => $this->badge($nodo),

            'paragraph' => $this->envolver('p', $this->hijos($nodo), [
                'class' => $this->clase($nodo, EsquemaCuerpo::CLASES_PARRAFO),
                'style' => $this->estilo($nodo),
            ]),
            'heading' => $this->encabezado($nodo),
            'bulletList' => $this->envolver('ul', $this->hijos($nodo)),
            'orderedList' => $this->envolver('ol', $this->hijos($nodo)),
            'listItem' => $this->envolver('li', $this->hijos($nodo)),
            'hardBreak' => '<br>',

            'table' => $this->tabla($nodo),
            'tableRow' => $this->envolver('tr', $this->hijos($nodo), [
                'class' => $this->clase($nodo, EsquemaCuerpo::CLASES_FILA),
            ]),
            'tableHeader' => $this->celdaCabecera($nodo),
            'tableCell' => $this->celda($nodo),

            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function hijos(array $nodo): string
    {
        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos)) {
            return '';
        }

        $html = '';

        foreach ($hijos as $hijo) {
            if (is_array($hijo)) {
                $html .= $this->nodo($hijo);
            }
        }

        return $html;
    }

    /**
     * Texto llano, envuelto en sus marcas de dentro hacia fuera.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function textoConMarcas(array $nodo): string
    {
        $texto = $nodo['text'] ?? '';

        if (! is_string($texto) || $texto === '') {
            return '';
        }

        $html = e($texto);

        $marcas = $nodo['marks'] ?? [];

        if (! is_array($marcas)) {
            return $html;
        }

        foreach ($marcas as $marca) {
            if (! is_array($marca)) {
                continue;
            }

            $tipo = $marca['type'] ?? null;

            if (! is_string($tipo) || ! EsquemaCuerpo::admiteMarca($tipo)) {
                continue;
            }

            $html = match ($tipo) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'cifra' => '<span class="cifra">'.$html.'</span>',
                'suave' => '<span class="suave">'.$html.'</span>',
                'link' => $this->enlace($marca, $html),
                default => $html,
            };
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $marca
     */
    private function enlace(array $marca, string $html): string
    {
        $atributos = $marca['attrs'] ?? [];
        $href = is_array($atributos) ? ($atributos['href'] ?? null) : null;

        // Un enlace que no se puede pintar deja el texto, no lo borra: lo que se
        // quita es la navegación, no lo que la frase dice.
        if (! is_string($href) || ! EsquemaCuerpo::enlaceSeguro($href)) {
            return $html;
        }

        return '<a href="'.e($href).'">'.$html.'</a>';
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function caja(array $nodo): string
    {
        $variante = $this->atributo($nodo, 'variante');
        $clases = EsquemaCuerpo::VARIANTES_CAJA[$variante] ?? EsquemaCuerpo::VARIANTES_CAJA['simple'];

        return $this->envolver('div', $this->hijos($nodo), [
            'class' => $clases,
            'style' => $this->estilo($nodo),
        ]);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function fichaFila(array $nodo): string
    {
        $clave = $this->atributo($nodo, 'clave') ?? '';

        return '<div class="ficha__clave">'.e($clave).'</div>'
            .'<div class="ficha__valor">'.$this->hijos($nodo).'</div>';
    }

    /**
     * Una cifra del resumen, siempre con su denominador.
     *
     * `de` es nulo cuando la cifra no tiene sobre qué contarse —los excluidos son
     * los que son—, y entonces no se pinta el `<span>`: «4 de » se lee como un
     * fallo de plantilla.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function cifraDato(array $nodo): string
    {
        $valor = $this->atributo($nodo, 'valor') ?? '—';
        $de = $this->atributo($nodo, 'de');
        $etiqueta = $this->atributo($nodo, 'etiqueta') ?? '';

        $denominador = $de === null || $de === ''
            ? ''
            : '<span class="cifras__de"> '.e($de).'</span>';

        return '<div class="cifras__dato">'
            .'<div class="cifras__valor">'.e($valor).$denominador.'</div>'
            .'<div class="cifras__etiqueta">'.e($etiqueta).'</div>'
            .'</div>';
    }

    /**
     * La barra por tramos y su leyenda.
     *
     * El nodo guarda **el reparto**, no el dibujo: así el SVG —lo único que sale
     * de aquí sin escapar— se genera en el momento y nunca pasa por el cuerpo
     * editable. Sin tramos no se pinta nada, ni el `<div>` vacío.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function grafica(array $nodo): string
    {
        $segmentos = [];

        $declarados = $nodo['attrs']['segmentos'] ?? [];

        if (is_array($declarados)) {
            foreach ($declarados as $segmento) {
                if (! is_array($segmento)) {
                    continue;
                }

                $clave = $segmento['clave'] ?? null;
                $etiqueta = $segmento['etiqueta'] ?? null;
                $valor = $segmento['valor'] ?? null;

                if (! is_string($clave) || ! is_string($etiqueta) || ! is_int($valor)) {
                    continue;
                }

                // La clave decide el color por `var(--estado-…)`: si no es un
                // tono conocido, el tramo saldría transparente.
                if (! array_key_exists($clave, EsquemaCuerpo::TONOS_BADGE)) {
                    continue;
                }

                $segmentos[] = new SegmentoEstado($clave, $etiqueta, $valor);
            }
        }

        $barra = $this->graficas->barraPorEstado($segmentos);

        if ($barra === '') {
            return '';
        }

        return '<div class="grafica">'.$barra.$this->hijos($nodo).'</div>';
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function badge(array $nodo): string
    {
        $tono = $this->atributo($nodo, 'tono');
        $clases = EsquemaCuerpo::TONOS_BADGE[$tono] ?? EsquemaCuerpo::TONOS_BADGE['neutro'];

        return '<span class="'.$clases.'">'.$this->hijos($nodo).'</span>';
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function encabezado(array $nodo): string
    {
        $nivel = $nodo['attrs']['level'] ?? 2;

        if (! is_int($nivel) || ! in_array($nivel, EsquemaCuerpo::NIVELES, true)) {
            $nivel = 2;
        }

        return $this->envolver('h'.$nivel, $this->hijos($nodo), [
            'class' => $this->clase($nodo, EsquemaCuerpo::CLASES_ENCABEZADO),
            'style' => $this->estilo($nodo),
        ]);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function tabla(array $nodo): string
    {
        $filas = $this->hijos($nodo);

        if ($filas === '') {
            return '';
        }

        /*
         * `<thead>` y `<tbody>` no son nodos del esquema: se deducen de la
         * primera fila. Un editor de tablas no da al usuario la noción de
         * «cabecera de tabla» como bloque aparte —da celdas de cabecera—, y
         * pedirle que mantenga los dos agrupadores a mano sería pedirle que
         * mantenga HTML. La cabecera importa: es lo que repite Chromium al
         * cortar la página, y sin ella una tabla de noventa y tres filas pierde
         * los títulos de columna a partir de la segunda hoja.
         */
        [$cabecera, $resto] = $this->partirCabecera($nodo);

        $html = '<table'.$this->atributosHtml([
            'class' => $this->clase($nodo, EsquemaCuerpo::CLASES_TABLA),
        ]).'>';

        if ($cabecera !== '') {
            $html .= '<thead>'.$cabecera.'</thead>';
        }

        return $html.'<tbody>'.$resto.'</tbody></table>';
    }

    /**
     * Separa la primera fila si es de cabeceras.
     *
     * @param  array<string, mixed>  $nodo
     * @return array{string, string}
     */
    private function partirCabecera(array $nodo): array
    {
        $filas = $nodo['content'] ?? [];

        if (! is_array($filas) || $filas === []) {
            return ['', ''];
        }

        $primera = reset($filas);
        $cabecera = '';

        if (is_array($primera) && $this->esFilaDeCabecera($primera)) {
            $cabecera = $this->nodo($primera);
            array_shift($filas);
        }

        $cuerpo = '';

        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $cuerpo .= $this->nodo($fila);
            }
        }

        return [$cabecera, $cuerpo];
    }

    /**
     * Una fila es de cabecera si todas sus celdas lo son.
     *
     * La fila de grupo de la tabla larga —un `<th colspan>` con el nombre del
     * tema— también cumpliría, y por eso se excluye explícitamente: va en el
     * `<tbody>`, entre las filas que agrupa, no repetida en cada página.
     *
     * @param  array<string, mixed>  $fila
     */
    private function esFilaDeCabecera(array $fila): bool
    {
        if (($fila['type'] ?? null) !== 'tableRow') {
            return false;
        }

        if (($fila['attrs']['clase'] ?? null) === 'grupo') {
            return false;
        }

        $celdas = $fila['content'] ?? [];

        if (! is_array($celdas) || $celdas === []) {
            return false;
        }

        foreach ($celdas as $celda) {
            if (! is_array($celda) || ($celda['type'] ?? null) !== 'tableHeader') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function celdaCabecera(array $nodo): string
    {
        $ancho = $this->atributo($nodo, 'ancho');
        $scope = $this->atributo($nodo, 'scope');

        return $this->envolver('th', $this->hijos($nodo), [
            'scope' => in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $scope : null,
            'colspan' => $this->entero($nodo, 'colspan'),
            'rowspan' => $this->entero($nodo, 'rowspan'),
            'style' => $ancho !== null && EsquemaCuerpo::anchoValido($ancho) ? 'width: '.$ancho : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function celda(array $nodo): string
    {
        return $this->envolver('td', $this->hijos($nodo), [
            'class' => $this->clase($nodo, EsquemaCuerpo::CLASES_CELDA),
            'colspan' => $this->entero($nodo, 'colspan'),
            'rowspan' => $this->entero($nodo, 'rowspan'),
        ]);
    }

    /**
     * La clase de un nodo, traducida por su mapa. Lo que no esté, no sale.
     *
     * @param  array<string, mixed>  $nodo
     * @param  array<string, string>  $mapa
     */
    private function clase(array $nodo, array $mapa): ?string
    {
        $clave = $this->atributo($nodo, 'clase');

        return $clave === null ? null : ($mapa[$clave] ?? null);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function estilo(array $nodo): ?string
    {
        $clave = $this->atributo($nodo, 'estilo');

        return $clave === null ? null : (EsquemaCuerpo::ESTILOS[$clave] ?? null);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function atributo(array $nodo, string $nombre): ?string
    {
        $valor = $nodo['attrs'][$nombre] ?? null;

        return is_string($valor) ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function entero(array $nodo, string $nombre): ?int
    {
        $valor = $nodo['attrs'][$nombre] ?? null;

        // 1 es el valor por defecto de `colspan`: escribirlo no dice nada y
        // ensucia el diff entre dos versiones del mismo documento.
        return is_int($valor) && $valor > 1 && $valor <= 64 ? $valor : null;
    }

    /**
     * @param  array<string, string|int|null>  $atributos
     */
    private function envolver(string $etiqueta, string $contenido, array $atributos = []): string
    {
        return '<'.$etiqueta.$this->atributosHtml($atributos).'>'.$contenido.'</'.$etiqueta.'>';
    }

    /**
     * @param  array<string, string|int|null>  $atributos
     */
    private function atributosHtml(array $atributos): string
    {
        $html = '';

        foreach ($atributos as $nombre => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $html .= ' '.$nombre.'="'.e((string) $valor).'"';
        }

        return $html;
    }
}
