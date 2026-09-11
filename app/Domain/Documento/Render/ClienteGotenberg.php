<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Excepciones\GeneracionFallida;

/**
 * Quien convierte el HTML en PDF.
 *
 * Es una interfaz para que los tests de contenido —que son casi todos— corran
 * sin levantar un contenedor, y para que se pueda afirmar sobre la
 * `SolicitudPdf` que se envió. La implementación real es `GotenbergHttp`.
 */
interface ClienteGotenberg
{
    /**
     * @return string Los bytes del PDF.
     *
     * @throws GeneracionFallida
     */
    public function pdf(SolicitudPdf $solicitud): string;
}
