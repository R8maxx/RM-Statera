<?php

declare(strict_types=1);

namespace Tests\Dobles;

use App\Domain\Documento\Cuerpo\RenderizadorCuerpo;
use App\Domain\Documento\Excepciones\GeneracionFallida;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Documento\Render\LectorPaginas;
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

    /** @var list<SolicitudPdf> Las pasadas de medida del índice, aparte de las impresiones. */
    public array $mediciones = [];

    /**
     * Cuántas páginas ocupa cada sección en la medida de mentira.
     *
     * Con el valor por defecto, cualquier documento con tres secciones pasa del
     * umbral de `IndiceDocumento` y lleva índice; a 1, uno de tres secciones se
     * queda corto.
     */
    public int $paginasPorSeccion = 3;

    public function __construct(private ?string $fallo = null) {}

    /**
     * Una medida inventada pero coherente: el índice en la página 1 y cada
     * sección `paginasPorSeccion` páginas después de la anterior, en el orden
     * de sus marcadores en el HTML.
     */
    public function medir(SolicitudPdf $solicitud): array
    {
        $this->mediciones[] = $solicitud;

        if ($this->fallo !== null) {
            throw GeneracionFallida::porGotenberg($this->fallo);
        }

        $patron = '/'.str_replace('%s', '(s-\d+)', preg_quote(RenderizadorCuerpo::MARCADOR, '/')).'/';
        preg_match_all($patron, $solicitud->html, $encontrados);

        $texto = "Índice\f";

        foreach ($encontrados[0] as $marcador) {
            $texto .= $marcador.str_repeat("\f", $this->paginasPorSeccion);
        }

        return LectorPaginas::desdeTexto($texto);
    }

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

    /**
     * El HTML de la última generación —portada y cuerpo, que se imprimen por
     * separado—, para afirmar sobre el documento entero.
     */
    public function html(): string
    {
        return ($this->ultima()->portada ?? '').$this->ultima()->html;
    }
}
