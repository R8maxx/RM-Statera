<?php

declare(strict_types=1);

namespace Tests\Dobles;

use App\Domain\Documento\Excepciones\GeneracionFallida;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Documento\Render\SolicitudPdf;

/**
 * Gotenberg de mentira: devuelve un PDF válido y **guarda lo que se le pidió**.
 *
 * Ésa es la idea del enfoque: probar el HTML es probar el documento, y Gotenberg
 * sólo es la impresora. Con la solicitud guardada se puede afirmar sobre el
 * cuerpo, la cabecera, el pie y los assets sin levantar un contenedor, que es lo
 * que permite que los tests de contenido sean rápidos y corran en cualquier
 * máquina.
 */
final class GotenbergFalso implements ClienteGotenberg
{
    /** @var list<SolicitudPdf> */
    public array $solicitudes = [];

    public function __construct(private ?string $fallo = null) {}

    public function pdf(SolicitudPdf $solicitud): string
    {
        $this->solicitudes[] = $solicitud;

        if ($this->fallo !== null) {
            throw GeneracionFallida::porGotenberg($this->fallo);
        }

        // Un PDF mínimo pero de verdad: empieza por `%PDF-` y termina en `%%EOF`,
        // que es lo que comprueba el cliente real antes de aceptar la respuesta.
        return "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    public function ultima(): SolicitudPdf
    {
        return $this->solicitudes[count($this->solicitudes) - 1];
    }

    /** El HTML del cuerpo de la última generación, para afirmar sobre él. */
    public function html(): string
    {
        return $this->ultima()->html;
    }
}
