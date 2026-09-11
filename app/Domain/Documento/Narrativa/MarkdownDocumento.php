<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convierte a HTML el Markdown que ha escrito la organización.
 *
 * **No usa `Str::markdown()`**, y no por gusto: ese helper monta un
 * `GithubFlavoredMarkdownConverter`, que trae tablas —y aquí los datos se
 * calculan, no se escriben—, autoenlaces, tachado y listas de tareas. Se monta
 * el convertidor a mano con el núcleo de CommonMark y nada más.
 *
 * Las tres opciones son las que cierran las puertas:
 *
 * - `html_input => 'escape'` — la única superficie de inyección del módulo. Lo
 *   que parezca una etiqueta sale escapado y visible, no interpretado.
 * - `allow_unsafe_links => false` — fuera `javascript:`, `data:` y `vbscript:`.
 * - `max_nesting_level` — una lista anidada de cuatrocientos niveles es una
 *   denegación de servicio escrita con guiones.
 *
 * Y encima va `NormalizarNarrativa`, que poda el árbol ya parseado.
 *
 * El resultado **no se almacena nunca**: lo que se guarda y lo que se congela en
 * la instantánea es el Markdown. El HTML es una función determinista de él, y el
 * PDF entregado ya está almacenado, así que un cambio futuro aquí no altera
 * ningún documento emitido.
 */
final class MarkdownDocumento
{
    /**
     * Seis niveles de anidamiento son más de los que cabe defender en un
     * documento de cumplimiento, y bastante menos de los que hacen daño.
     */
    private const ANIDAMIENTO_MAXIMO = 6;

    private readonly MarkdownConverter $convertidor;

    private readonly MarkdownConverter $convertidorDeEditor;

    public function __construct()
    {
        $this->convertidor = $this->montar(desplazarEncabezados: true);
        $this->convertidorDeEditor = $this->montar(desplazarEncabezados: false);
    }

    private function montar(bool $desplazarEncabezados): MarkdownConverter
    {
        $entorno = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => self::ANIDAMIENTO_MAXIMO,
        ]);

        $entorno->addExtension(new CommonMarkCoreExtension);
        $entorno->addEventListener(
            DocumentParsedEvent::class,
            new NormalizarNarrativa($desplazarEncabezados),
        );

        return new MarkdownConverter($entorno);
    }

    /**
     * El HTML de un texto, o cadena vacía si no hay nada que pintar.
     *
     * La cadena vacía se contesta sin pasar por el parser: un hueco vacío es el
     * caso normal —la mayoría lo están— y no tiene sentido montar un árbol para
     * no pintar nada.
     */
    public function aHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return trim((string) $this->convertidor->convert($markdown));
    }

    /**
     * El HTML con el que se ALIMENTA el editor.
     *
     * Igual de saneado, pero **sin bajar los encabezados**: dentro del editor un
     * «Título» es un `h2`, y si se le diera el HTML del documento —donde ya es
     * un `h3`— bajaría otro nivel en cada guardado hasta tocar fondo. El
     * desplazamiento es maquetación del PDF, no contenido.
     */
    public function aHtmlDeEditor(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return trim((string) $this->convertidorDeEditor->convert($markdown));
    }

    /**
     * Normaliza el Markdown antes de guardarlo.
     *
     * Los finales de línea de Windows los mete cualquiera que pegue desde Word,
     * y CommonMark los trata distinto: sin esto salen saltos que nadie escribió.
     * Y tres líneas en blanco seguidas no separan más que dos.
     */
    public function normalizar(?string $markdown): string
    {
        if ($markdown === null) {
            return '';
        }

        $limpio = str_replace(["\r\n", "\r"], "\n", $markdown);
        $limpio = (string) preg_replace("/\n{3,}/", "\n\n", $limpio);

        return trim($limpio);
    }
}
