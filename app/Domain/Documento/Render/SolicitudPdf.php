<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

/**
 * Todo lo que hay que mandarle a Gotenberg para producir un PDF.
 *
 * Existe como objeto y no como una lista de argumentos para que el doble de los
 * tests pueda **guardar lo que se le pidió** y afirmar sobre ello: el HTML, la
 * cabecera, el pie y los assets. Probar el HTML es probar el documento;
 * Gotenberg sólo es la impresora.
 *
 * @phpstan-type Metadatos array<string, string>
 */
final readonly class SolicitudPdf
{
    /**
     * @param  string  $html  El cuerpo completo, que viaja como `index.html`.
     * @param  string  $cabecera  Documento HTML autónomo: Chromium lo renderiza en un contexto aparte que no hereda CSS ni fuentes.
     * @param  string  $pie  Ídem.
     * @param  list<AssetDocumento>  $assets  CSS y fuentes, que viajan en el mismo multipart y se referencian por nombre.
     * @param  array<string, string>  $metadatos  Los del PDF: Title, Author, Subject, Creator.
     */
    public function __construct(
        public string $html,
        public string $cabecera,
        public string $pie,
        public array $assets = [],
        public array $metadatos = [],
    ) {}

    /** @return list<string> Los nombres de fichero de los assets, para poder afirmar sobre ellos. */
    public function nombresDeAssets(): array
    {
        return array_map(static fn (AssetDocumento $asset): string => $asset->nombre, $this->assets);
    }
}
