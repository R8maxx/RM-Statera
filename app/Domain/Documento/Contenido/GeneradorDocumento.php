<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;

/**
 * Una clase por tipo de documento (§ 4 del stack).
 *
 * Cada una sabe hacer una consulta y devolver datos; de renderizar, llamar a
 * Gotenberg, calcular el hash, subir y registrar la versión se encarga un
 * servicio común. Así el sexto documento cuesta una clase y una plantilla, no
 * una tubería nueva.
 */
interface GeneradorDocumento
{
    public function tipo(): TipoDocumento;

    /**
     * @param  array<string, mixed>  $parametros  Lo que se pidió al generar.
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento;
}
