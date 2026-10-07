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

    /**
     * Imprime el cuerpo sin PDF/A y dice en qué página cayó cada marcador.
     *
     * Es la pasada de medida del índice (`IndiceDocumento`): el HTML lleva un
     * `RenderizadorCuerpo::MARCADOR` al principio de cada sección y aquí se
     * busca cada uno en el texto del PDF. La portada no se imprime: el índice
     * cuenta como el pie, desde la primera página del cuerpo.
     *
     * @return array{paginas: array<string, int>, total: int} Id de sección => página, y el total.
     *
     * @throws GeneracionFallida
     */
    public function medir(SolicitudPdf $solicitud): array;
}
