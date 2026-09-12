<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * HTML → nodos del cuerpo.
 *
 * Existe por una razón concreta y acotada: **la narrativa que las organizaciones
 * ya tienen escrita**. Antes de esta entrega los documentos se redactaban en
 * once huecos de Markdown, y estrenar el cuerpo editable desde el esqueleto de
 * fábrica habría dejado fuera lo que alguien escribió —las conclusiones, las
 * limitaciones propias, la aprobación—. Eso no es empezar de cero: es perder
 * texto, y perderlo en silencio, que es el fallo que este proyecto persigue.
 *
 * Se parte del HTML y no del Markdown porque `MarkdownDocumento` ya es un
 * parser de verdad, probado, que escapa la entrada y poda lo peligroso. Escribir
 * un segundo parser de Markdown para lo mismo sería tener dos sitios donde se
 * decide qué es una negrita.
 *
 * **No es una puerta de entrada de HTML al documento.** Lo que sale de aquí son
 * nodos del esquema y nada más: una etiqueta que no esté contemplada aporta sus
 * hijos y desaparece ella, y un atributo que no esté declarado no se mira.
 */
final class HtmlANodos
{
    /**
     * @return list<array<string, mixed>>
     */
    public function bloques(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $documento = new DOMDocument;

        // `LIBXML_NOERROR`: el HTML de entrada puede traer cualquier cosa y no
        // queremos avisos de libxml por la salida de un comando de consola.
        $previo = libxml_use_internal_errors(true);
        $documento->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $raiz = $documento->documentElement;

        return $raiz instanceof DOMElement ? $this->hijosComoBloques($raiz) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hijosComoBloques(DOMElement $elemento): array
    {
        $bloques = [];
        $sueltos = [];

        foreach ($elemento->childNodes as $hijo) {
            $bloque = $this->bloque($hijo);

            if ($bloque !== null) {
                // El texto suelto que hubiera antes de un bloque no se tira: se
                // cierra en su propio párrafo.
                if ($sueltos !== []) {
                    $bloques[] = Nodo::de('paragraph', [], $sueltos);
                    $sueltos = [];
                }

                $bloques[] = $bloque;

                continue;
            }

            $sueltos = [...$sueltos, ...$this->enLinea($hijo)];
        }

        if ($sueltos !== []) {
            $bloques[] = Nodo::de('paragraph', [], $sueltos);
        }

        return $bloques;
    }

    /**
     * @return array<string, mixed>|null null si no es un bloque
     */
    private function bloque(DOMNode $nodo): ?array
    {
        if (! $nodo instanceof DOMElement) {
            return null;
        }

        $etiqueta = mb_strtolower($nodo->nodeName);

        return match ($etiqueta) {
            'p' => Nodo::de('paragraph', [], $this->hijosEnLinea($nodo)),

            /*
             * Los encabezados del usuario venían ya desplazados a h3/h4 por
             * `MarkdownDocumento::aHtml()`, que los bajaba dos niveles para no
             * romper la jerarquía que exige PDF/UA. Se respetan: lo que escribió
             * está escrito bajo un `<h2>` que pone la sección, y subirlos ahora
             * cambiaría el índice del documento sin que nadie lo pidiera.
             */
            'h1', 'h2', 'h3' => Nodo::de('heading', ['level' => 3], $this->hijosEnLinea($nodo)),
            'h4', 'h5', 'h6' => Nodo::de('heading', ['level' => 4], $this->hijosEnLinea($nodo)),

            'ul' => Nodo::de('bulletList', [], $this->puntos($nodo)),
            'ol' => Nodo::de('orderedList', [], $this->puntos($nodo)),

            // `blockquote` no existe en el esquema: sus párrafos se quedan, la
            // cita desaparece. Nada de lo que dice el documento se pierde.
            'blockquote', 'div', 'section' => Nodo::de('grupo', [], $this->hijosComoBloques($nodo)),

            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function puntos(DOMElement $lista): array
    {
        $puntos = [];

        foreach ($lista->childNodes as $hijo) {
            if (! $hijo instanceof DOMElement || mb_strtolower($hijo->nodeName) !== 'li') {
                continue;
            }

            $bloques = $this->hijosComoBloques($hijo);

            $puntos[] = Nodo::de('listItem', [], $bloques === []
                ? [Nodo::de('paragraph', [], $this->hijosEnLinea($hijo))]
                : $bloques);
        }

        return $puntos;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hijosEnLinea(DOMElement $elemento): array
    {
        $nodos = [];

        foreach ($elemento->childNodes as $hijo) {
            $nodos = [...$nodos, ...$this->enLinea($hijo)];
        }

        return $nodos;
    }

    /**
     * @param  list<string>  $marcas
     * @return list<array<string, mixed>>
     */
    private function enLinea(DOMNode $nodo, array $marcas = []): array
    {
        if ($nodo instanceof DOMText) {
            $texto = $nodo->textContent;

            return trim($texto) === '' ? [] : [Nodo::texto($texto, $marcas)];
        }

        if (! $nodo instanceof DOMElement) {
            return [];
        }

        $etiqueta = mb_strtolower($nodo->nodeName);

        if ($etiqueta === 'br') {
            return [Nodo::de('hardBreak')];
        }

        // Un enlace lleva su `href` y no una marca suelta, así que se resuelve
        // aparte; y si el protocolo no pasa el filtro, se queda el texto.
        if ($etiqueta === 'a') {
            $href = $nodo->getAttribute('href');

            if ($href !== '' && EsquemaCuerpo::enlaceSeguro($href)) {
                return array_map(
                    static function (array $hijo) use ($href): array {
                        $hijo['marks'] = [...($hijo['marks'] ?? []), ['type' => 'link', 'attrs' => ['href' => $href]]];

                        return $hijo;
                    },
                    $this->hijosEnLinea($nodo),
                );
            }

            return $this->hijosEnLinea($nodo);
        }

        $marca = match ($etiqueta) {
            'strong', 'b' => 'bold',
            'em', 'i' => 'italic',
            'code' => 'cifra',
            default => null,
        };

        $conMarca = $marca === null ? $marcas : [...$marcas, $marca];

        $nodos = [];

        foreach ($nodo->childNodes as $hijo) {
            $nodos = [...$nodos, ...$this->enLinea($hijo, $conMarca)];
        }

        return $nodos;
    }
}
