<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

/**
 * Un fichero que acompaña al HTML en el multipart: la hoja de estilos o una
 * fuente.
 *
 * Gotenberg deja todos los ficheros del multipart en el mismo directorio
 * temporal, así que el HTML los referencia por su nombre a secas
 * (`href="documento.css"`) y Chromium los resuelve como `file:///tmp/...`, que es
 * justo lo que permite la allow-list del contenedor. Ninguna URL remota entra en
 * el documento: ni nuestra, ni ajena.
 */
final readonly class AssetDocumento
{
    public function __construct(
        public string $nombre,
        public string $contenido,
    ) {}
}
