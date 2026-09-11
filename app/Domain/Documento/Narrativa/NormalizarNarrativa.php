<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;

/**
 * Poda y encaja el árbol de lo que ha escrito la organización, antes de pintarlo.
 *
 * Es la segunda capa de defensa. La primera es el esquema del editor, que sólo
 * ofrece lo que se admite; ésta vale también cuando alguien pega desde Word,
 * escribe Markdown a mano o llama a la ruta directamente.
 *
 * Hace tres cosas, y ninguna es cosmética:
 *
 * 1. **Baja los encabezados a `h3`/`h4`.** El `<h2>` de cada sección lo pone
 *    siempre la plantilla, así que un `#` del usuario partiría la jerarquía que
 *    PDF/UA exige y competiría con el título de la portada. Con esto es
 *    **imposible** producir un `<h1>` o saltarse un nivel.
 * 2. **Quita las imágenes.** Esto es lo que impide tumbar la generación entera:
 *    `failOnResourceLoadingFailed()` está encendido y la allow-list de Gotenberg
 *    es `^(file:///tmp/|data:).*`, así que un `![foto](https://…)` escrito por
 *    cualquiera haría fallar el PDF con un error que apunta a Gotenberg, que no
 *    tiene ninguna culpa. Un `<a href>` sí pasa: un enlace no se descarga al
 *    imprimir.
 * 3. **Quita el HTML crudo**, encima de que el convertidor ya va con
 *    `html_input => 'escape'`. Cinturón y tirantes sobre la única superficie de
 *    inyección que tiene el módulo.
 */
final class NormalizarNarrativa
{
    /** El `<h2>` es de la plantilla; lo del usuario empieza por debajo. */
    private const NIVEL_MINIMO = 3;

    private const NIVEL_MAXIMO = 4;

    /**
     * @param  bool  $desplazarEncabezados  Sólo al pintar el documento.
     *
     * El desplazamiento es una decisión de **maquetación del PDF**, no del
     * contenido guardado: dentro del editor un «Título» tiene que seguir siendo
     * un `h2`, o al volver a guardar bajaría otro nivel, y otro, y otro. Lo que
     * se almacena es `##`; lo que se imprime es `<h3>`.
     */
    public function __construct(private readonly bool $desplazarEncabezados = true) {}

    public function __invoke(DocumentParsedEvent $evento): void
    {
        /*
         * Se recoge primero y se modifica después. Mutar el árbol mientras se
         * recorre deja el recorrido en un estado que depende del orden en que se
         * borre, y eso es un fallo que aparece sólo con ciertos documentos.
         */
        $aPodar = [];

        $walker = $evento->getDocument()->walker();

        while ($paso = $walker->next()) {
            if (! $paso->isEntering()) {
                continue;
            }

            $nodo = $paso->getNode();

            if ($nodo instanceof Heading) {
                if ($this->desplazarEncabezados) {
                    $nodo->setLevel($this->nivelDestino($nodo->getLevel()));
                }

                continue;
            }

            if ($nodo instanceof Image || $nodo instanceof HtmlBlock || $nodo instanceof HtmlInline) {
                $aPodar[] = $nodo;
            }
        }

        foreach ($aPodar as $nodo) {
            $this->podar($nodo);
        }
    }

    /**
     * `#` y `##` bajan a `h3`; `###` y más abajo, a `h4`.
     *
     * El editor sólo ofrece dos niveles («Título» y «Subtítulo»), que caen justo
     * en esos dos. Lo que llega escrito a mano se acota al mismo rango.
     */
    private function nivelDestino(int $nivel): int
    {
        return max(self::NIVEL_MINIMO, min(self::NIVEL_MAXIMO, $nivel + 1));
    }

    /**
     * Saca el nodo y conserva lo que llevaba dentro.
     *
     * En el caso de una imagen, lo que lleva dentro es su texto alternativo:
     * quien lo escribió quería decir algo, y perderlo del todo sería peor que
     * perder la imagen.
     */
    private function podar(Node $nodo): void
    {
        foreach ($nodo->children() as $hijo) {
            $nodo->insertBefore($hijo);
        }

        $nodo->detach();
    }
}
