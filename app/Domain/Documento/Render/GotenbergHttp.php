<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Excepciones\GeneracionFallida;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use GuzzleHttp\Client;
use Throwable;

/**
 * El cliente real: habla con el contenedor de Gotenberg.
 *
 * Cuatro reglas del § 4 del stack, hechas código:
 *
 * 1. **Se envía el HTML y los assets en el multipart, nunca una URL.** Gotenberg
 *    descarga lo que se le pase, así que darle una URL nuestra sería un SSRF con
 *    el problema añadido de autenticar la petición. Todo viaja dentro, y el
 *    resultado es reproducible.
 * 2. **Cabecera y pie como ficheros aparte**, con código, versión, fecha,
 *    clasificación y «página X de Y».
 * 3. **PDF/A-3b**, que es el formato de conservación a largo plazo: estos
 *    registros hay que guardarlos años.
 * 4. Nunca en el ciclo de petición. De eso responde quien llama.
 *
 * **`pdfua()` se queda apagado**, y es deliberado: Gotenberg rechaza la petición
 * ENTERA si el documento no es conforme, así que encenderlo antes de que las
 * plantillas lleven `lang`, `<th scope>`, jerarquía de encabezados correcta y
 * `<title>` en cada SVG convierte la generación en algo que falla por sorpresa.
 * `generateTaggedPdf()` sí va: es su prerrequisito y no rompe nada.
 */
final readonly class GotenbergHttp implements ClienteGotenberg
{
    /**
     * El tamaño de la hoja y sus márgenes viven en `GeometriaPagina`.
     *
     * Estaban aquí, que es donde nacieron, y salieron cuando el editor pasó a
     * dibujar la misma hoja debajo del cursor: dos sitios que tienen que estar
     * de acuerdo no pueden tener cada uno su copia.
     */
    public function __construct(
        private string $url,
        private int $timeout,
    ) {}

    public function pdf(SolicitudPdf $solicitud): string
    {
        $peticion = Gotenberg::chromium($this->url)->pdf()
            ->paperSize(GeometriaPagina::ANCHO, GeometriaPagina::ALTO)
            ->margins(
                GeometriaPagina::MARGEN_SUPERIOR,
                GeometriaPagina::MARGEN_INFERIOR,
                GeometriaPagina::MARGEN_LATERAL,
                GeometriaPagina::MARGEN_LATERAL,
            )
            ->printBackground()

            // Prerrequisito de PDF/UA y, de paso, lo que hace que un lector de
            // pantalla distinga una cabecera de tabla de una celda cualquiera.
            ->generateTaggedPdf()

            // Marcadores laterales. Es el índice que se puede tener sin conocer
            // la paginación de Chromium.
            ->generateDocumentOutline()

            ->header(Stream::string('header.html', $solicitud->cabecera))
            ->footer(Stream::string('footer.html', $solicitud->pie))

            /*
             * La bandera más valiosa del cliente. Sin ella, una fuente que no
             * cargue hace que Chromium sustituya en silencio: el PDF/A sale
             * válido y feo, y nadie se entera hasta que alguien lo abre. Con
             * ella, es un fallo con mensaje.
             */
            ->failOnResourceLoadingFailed()
            ->failOnConsoleExceptions()

            ->metadata($solicitud->metadatos)
            ->pdfa('PDF/A-3b');

        if ($solicitud->assets !== []) {
            $peticion = $peticion->assets(...array_map(
                static fn (AssetDocumento $asset): Stream => Stream::string($asset->nombre, $asset->contenido),
                $solicitud->assets,
            ));
        }

        try {
            /*
             * Cliente HTTP explícito, y no el que descubre `Psr18ClientDiscovery`.
             * La cadena de tiempos tiene que quedar así, en este orden:
             *
             *     Gotenberg --api-timeout=120s  <  este cliente  <  timeout del job
             *
             * Con el descubierto (30 s por defecto) una SoA grande falla de
             * forma intermitente y el error apunta a Gotenberg, que no tiene
             * ninguna culpa.
             */
            $respuesta = Gotenberg::send(
                $peticion->html(Stream::string('index.html', $solicitud->html)),
                new Client(['timeout' => $this->timeout]),
            );
        } catch (Throwable $e) {
            throw GeneracionFallida::porGotenberg($e->getMessage(), $e);
        }

        $pdf = (string) $respuesta->getBody();

        if (! str_starts_with($pdf, '%PDF-')) {
            throw GeneracionFallida::porGotenberg('la respuesta no es un PDF.');
        }

        return $pdf;
    }
}
