<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;

/**
 * El cuerpo del documento, escrito en un `.docx`.
 *
 * Es el hermano de `RenderizadorCuerpo`: el mismo árbol, el mismo vocabulario
 * cerrado de `EsquemaCuerpo`, otro destino. Antes esto no existía y el Word
 * montaba una secuencia fija —portada, textos, resumen, tabla, limitaciones—
 * desde `ContenidoDocumento`, que era el camino de cuando el documento tenía
 * once huecos de texto y no se podía tocar nada más. **Desde que el documento se
 * edita entero, esa secuencia era literalmente otro documento**: lo redactado no
 * aparecía, y el orden era el que decidía esta clase y no quien lo escribió.
 *
 * Lo que **no** se traduce es el lenguaje de color y forma. El `.docx` es una
 * copia de trabajo para pegar en un informe propio: aquí interesan el texto, la
 * jerarquía y las tablas. Los tonos de badge llegan como texto, la barra por
 * tramos como sus cifras y el filete de la portada no llega en absoluto.
 *
 * Y como en el renderizador, **un nodo que no esté declarado no se escribe**. No
 * hay rama «y si no, ponlo tal cual».
 */
final class CuerpoAWord
{
    /** Twips por pulgada, que es la unidad en la que OOXML mide. */
    private const TWIPS_POR_PULGADA = 1440;

    /** El gris de los textos secundarios, el mismo del pie. */
    private const SUAVE = '5D6C72';

    private const TEAL = '006467';

    /**
     * Los anchos de la tabla que se está escribiendo, en twips.
     *
     * Las celdas del cuerpo no declaran ancho —sólo lo hacen las de cabecera— y
     * en OOXML cada celda lleva el suyo. Se toman de la primera fila y se
     * aplican por posición, que es exactamente lo que hace `table-layout: fixed`
     * en el PDF.
     *
     * @var list<int>
     */
    private array $anchos = [];

    public function __construct(private readonly int $anchoUtil) {}

    /**
     * @param  array<string, mixed>  $cuerpo  documento de ProseMirror
     */
    public function __invoke(AbstractContainer $destino, array $cuerpo): void
    {
        $this->hijos($destino, $cuerpo);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function nodo(AbstractContainer $destino, array $nodo): void
    {
        $tipo = $nodo['type'] ?? null;

        if (! is_string($tipo) || ! EsquemaCuerpo::admite($tipo)) {
            return;
        }

        match ($tipo) {
            // Contenedores que no aportan forma en Word: sólo sus hijos.
            'doc', 'grupo', 'portada', 'seccion', 'caja', 'limitaciones', 'leyenda' => $this->hijos($destino, $nodo),

            'marcaPortada' => $destino->addText($this->texto($nodo), ['size' => 8, 'bold' => true, 'color' => self::TEAL]),
            'pieDePortada', 'nota' => $destino->addText($this->texto($nodo), ['size' => 7, 'color' => self::SUAVE]),

            'ficha' => $this->ficha($destino, $nodo),
            'cifras' => $this->cifras($destino, $nodo),
            'grafica' => $this->grafica($destino, $nodo),

            'paragraph' => $this->parrafo($destino, $nodo),
            'heading' => $this->encabezado($destino, $nodo),
            'bulletList', 'orderedList' => $this->lista($destino, $nodo),

            'table' => $this->tabla($destino, $nodo),

            // `fileteMarca` es una barra de color, `badge` y el resto de piezas
            // en línea se escriben desde el texto de su contenedor.
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function hijos(AbstractContainer $destino, array $nodo): void
    {
        foreach ($this->contenido($nodo) as $hijo) {
            $this->nodo($destino, $hijo);
        }
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<array<string, mixed>>
     */
    private function contenido(array $nodo): array
    {
        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos)) {
            return [];
        }

        return array_values(array_filter($hijos, is_array(...)));
    }

    /* ------------------------------------------------------------- Texto */

    /**
     * El texto llano de un nodo y de todo lo que cuelga de él.
     *
     * Para los sitios donde Word no admite texto con formato: un título, la
     * clave de una ficha, el ítem de una lista.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function texto(array $nodo): string
    {
        if (($nodo['type'] ?? null) === 'text') {
            return is_string($nodo['text'] ?? null) ? $nodo['text'] : '';
        }

        if (($nodo['type'] ?? null) === 'hardBreak') {
            return ' ';
        }

        $texto = '';

        foreach ($this->contenido($nodo) as $hijo) {
            $texto .= $this->texto($hijo);
        }

        return $texto;
    }

    /**
     * Escribe los hijos de un nodo dentro de una línea, con sus marcas.
     *
     * El estilo base baja hasta cada trozo en vez de ponerse en la línea: un
     * `TextRun` de PHPWord no tiene estilo de fuente propio —y no protesta si se
     * le pide, se lo traga un `__call`—, así que quien lo lleva es cada `addText`.
     *
     * @param  array<string, mixed>  $nodo
     * @param  array<string, mixed>  $base
     */
    private function enLinea(TextRun $linea, array $nodo, array $base = []): void
    {
        foreach ($this->contenido($nodo) as $hijo) {
            $tipo = $hijo['type'] ?? null;

            if ($tipo === 'hardBreak') {
                $linea->addTextBreak();

                continue;
            }

            if ($tipo === 'text') {
                $this->trozo($linea, $hijo, $base);

                continue;
            }

            // `badge` y cualquier otro contenedor en línea: su texto, sin más.
            if (is_string($tipo) && EsquemaCuerpo::admite($tipo)) {
                $this->enLinea($linea, $hijo, $base);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @param  array<string, mixed>  $base
     */
    private function trozo(TextRun $linea, array $nodo, array $base): void
    {
        $texto = $nodo['text'] ?? '';

        if (! is_string($texto) || $texto === '') {
            return;
        }

        $estilo = $base;
        $enlace = null;

        foreach ($nodo['marks'] ?? [] as $marca) {
            if (! is_array($marca)) {
                continue;
            }

            $tipo = $marca['type'] ?? null;

            if (! is_string($tipo) || ! EsquemaCuerpo::admiteMarca($tipo)) {
                continue;
            }

            match ($tipo) {
                'bold' => $estilo['bold'] = true,
                'italic' => $estilo['italic'] = true,
                'suave' => $estilo['color'] = self::SUAVE,
                'link' => $enlace = $this->href($marca),
                default => null,
            };
        }

        // Un enlace que no se puede escribir deja el texto: lo que se pierde es
        // la navegación, no lo que la frase dice.
        if ($enlace !== null) {
            $linea->addLink($enlace, $texto, $estilo);

            return;
        }

        $linea->addText($texto, $estilo);
    }

    /**
     * @param  array<string, mixed>  $marca
     */
    private function href(array $marca): ?string
    {
        $atributos = $marca['attrs'] ?? [];
        $href = is_array($atributos) ? ($atributos['href'] ?? null) : null;

        if (! is_string($href) || ! EsquemaCuerpo::enlaceSeguro($href)) {
            return null;
        }

        return $href;
    }

    /* ---------------------------------------------------------- Bloques */

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function parrafo(AbstractContainer $destino, array $nodo): void
    {
        $clase = $this->atributo($nodo, 'clase');
        $suave = in_array($clase, ['suave', 'pequeno_suave', 'vacio'], true);
        $pequeno = in_array($clase, ['pequeno', 'pequeno_suave'], true);

        $base = [];

        if ($pequeno) {
            $base['size'] = 8;
        }

        if ($suave) {
            $base['color'] = self::SUAVE;
        }

        $this->enLinea(
            $destino->addTextRun($clase === 'subtitulo_portada' ? ['spaceAfter' => 240] : null),
            $nodo,
            $base,
        );
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function encabezado(AbstractContainer $destino, array $nodo): void
    {
        $nivel = $nodo['attrs']['level'] ?? 2;

        if (! is_int($nivel) || ! in_array($nivel, EsquemaCuerpo::NIVELES, true)) {
            $nivel = 2;
        }

        // `addTitle` sólo acepta texto llano: un encabezado con parte en negrita
        // no es un caso que se dé, y partirlo en trozos rompería el estilo.
        $destino->addTitle($this->texto($nodo), $nivel);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function lista(AbstractContainer $destino, array $nodo): void
    {
        foreach ($this->contenido($nodo) as $item) {
            $texto = trim($this->texto($item));

            if ($texto === '') {
                continue;
            }

            $destino->addListItem($texto, 0, ['size' => 8], 'vinetas');
        }
    }

    /**
     * La ficha de la portada, como tabla de clave y valor.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function ficha(AbstractContainer $destino, array $nodo): void
    {
        $filas = $this->contenido($nodo);

        if ($filas === []) {
            return;
        }

        $tabla = $destino->addTable('tabla');
        $anchoClave = 2200;

        foreach ($filas as $fila) {
            $celdas = $tabla->addRow();
            $celdas->addCell($anchoClave)->addText(
                (string) ($this->atributo($fila, 'clave') ?? ''),
                ['size' => 8, 'color' => self::SUAVE],
            );

            $this->enLinea(
                $celdas->addCell($this->anchoUtil - $anchoClave)->addTextRun(),
                $fila,
                ['size' => 9],
            );
        }
    }

    /**
     * Las cifras del resumen, en una fila de etiquetas y otra de valores.
     *
     * Cada cifra con su denominador, igual que en el PDF: «4 de 93» y «4» no
     * dicen lo mismo.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function cifras(AbstractContainer $destino, array $nodo): void
    {
        $datos = $this->contenido($nodo);

        if ($datos === []) {
            return;
        }

        $ancho = intdiv($this->anchoUtil, count($datos));
        $tabla = $destino->addTable('tabla');

        $etiquetas = $tabla->addRow();

        foreach ($datos as $dato) {
            $etiquetas->addCell($ancho)->addText(
                (string) ($this->atributo($dato, 'etiqueta') ?? ''),
                ['bold' => true, 'size' => 8],
            );
        }

        $valores = $tabla->addRow();

        foreach ($datos as $dato) {
            $de = (string) ($this->atributo($dato, 'de') ?? '');

            $valores->addCell($ancho)->addText(trim(
                ($this->atributo($dato, 'valor') ?? '—').' '.$de
            ));
        }
    }

    /**
     * La barra por tramos, como sus cifras.
     *
     * El nodo guarda el reparto y el dibujo lo hace `GraficaSvg` al imprimir. En
     * una copia de trabajo el dibujo no aporta nada que no diga el reparto, y un
     * SVG incrustado en un `.docx` es un adjunto que Word abre a su manera.
     *
     * @param  array<string, mixed>  $nodo
     */
    private function grafica(AbstractContainer $destino, array $nodo): void
    {
        $segmentos = $nodo['attrs']['segmentos'] ?? [];

        if (! is_array($segmentos) || $segmentos === []) {
            return;
        }

        $tramos = [];

        foreach ($segmentos as $segmento) {
            if (! is_array($segmento)) {
                continue;
            }

            $etiqueta = $segmento['etiqueta'] ?? null;
            $valor = $segmento['valor'] ?? null;

            if (is_string($etiqueta) && is_int($valor)) {
                $tramos[] = $etiqueta.': '.$valor;
            }
        }

        if ($tramos === []) {
            return;
        }

        $destino->addText(implode(' · ', $tramos), ['size' => 8, 'color' => self::SUAVE]);
    }

    /* ----------------------------------------------------------- Tablas */

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function tabla(AbstractContainer $destino, array $nodo): void
    {
        $filas = $this->contenido($nodo);

        if ($filas === []) {
            return;
        }

        $this->anchos = $this->anchosDe($filas[0]);

        $tabla = $destino->addTable('tabla');

        foreach ($filas as $indice => $fila) {
            $this->fila($tabla, $fila, $indice === 0);
        }

        $this->anchos = [];
    }

    /**
     * Los anchos de columna, escalados al ancho útil de la hoja.
     *
     * Vienen en pulgadas y medidos para el PDF, cuyo ancho útil no es el del
     * `.docx`: sin escalar, una tabla de diez columnas se sale de la página.
     *
     * @param  array<string, mixed>  $cabecera
     * @return list<int>
     */
    private function anchosDe(array $cabecera): array
    {
        $celdas = $this->contenido($cabecera);
        $pulgadas = [];

        foreach ($celdas as $celda) {
            $ancho = $this->atributo($celda, 'ancho');

            $pulgadas[] = is_string($ancho) && str_ends_with($ancho, 'in')
                ? (float) substr($ancho, 0, -2)
                : 0.0;
        }

        $total = array_sum($pulgadas);

        // Sin anchos declarados, reparto a partes iguales.
        if ($total <= 0.0) {
            $columnas = max(count($celdas), 1);

            return array_fill(0, $columnas, intdiv($this->anchoUtil, $columnas));
        }

        $escala = $this->anchoUtil / ($total * self::TWIPS_POR_PULGADA);

        return array_map(
            fn (float $pulgada): int => max((int) round($pulgada * self::TWIPS_POR_PULGADA * $escala), 200),
            $pulgadas,
        );
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function fila(Table $tabla, array $fila, bool $esCabecera): void
    {
        $celdas = $this->contenido($fila);

        if ($celdas === []) {
            return;
        }

        // La cabecera se repite en cada página: una tabla de noventa y tres
        // filas sin ella deja de poder leerse a partir de la segunda.
        $destino = $tabla->addRow(null, $esCabecera ? ['tblHeader' => true] : null);

        // Una fila de grupo es una sola celda que cruza la tabla entera.
        $esGrupo = $this->atributo($fila, 'clase') === 'grupo';

        foreach ($celdas as $indice => $celda) {
            $this->celda($destino->addCell(
                $esGrupo ? $this->anchoUtil : ($this->anchos[$indice] ?? intdiv($this->anchoUtil, count($celdas))),
                $this->estiloDeCelda($celda, $esGrupo, count($this->anchos)),
            ), $celda, $esCabecera || $esGrupo);
        }
    }

    /**
     * @param  array<string, mixed>  $celda
     * @return array<string, mixed>
     */
    private function estiloDeCelda(array $celda, bool $esGrupo, int $columnas): array
    {
        $estilo = [];

        if ($esGrupo) {
            $estilo['gridSpan'] = max($columnas, 1);
            $estilo['bgColor'] = 'D5F4F4';

            return $estilo;
        }

        $cruce = $this->atributo($celda, 'colspan');

        if (is_int($cruce) && $cruce > 1) {
            $estilo['gridSpan'] = $cruce;
        }

        return $estilo;
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function celda(Cell $destino, array $nodo, bool $destacada): void
    {
        $linea = $destino->addTextRun();
        $base = ['size' => 7, 'bold' => $destacada];

        $hijos = $this->contenido($nodo);

        // Una celda del cuerpo trae texto suelto; una de la tabla larga trae
        // párrafos y notas. Los dos casos caben escribiendo lo que haya.
        foreach ($hijos as $hijo) {
            if (in_array($hijo['type'] ?? null, ['paragraph', 'nota'], true)) {
                $this->enLinea($linea, $hijo, $base);

                continue;
            }

            $this->enLinea($linea, ['content' => [$hijo]], $base);
        }
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function atributo(array $nodo, string $nombre): mixed
    {
        $atributos = $nodo['attrs'] ?? [];

        return is_array($atributos) ? ($atributos[$nombre] ?? null) : null;
    }
}
