<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Cuerpo\RenderizadorCuerpo;
use App\Domain\Documento\Excepciones\GeneracionFallida;
use Illuminate\Support\Facades\Process;

/**
 * Lee de un PDF en qué página cayó cada marcador de sección.
 *
 * Con `pdftotext` (poppler-utils, en la imagen PHP), que separa las páginas con
 * un salto de página: la página de un marcador es el trozo en el que aparece.
 * Ninguna dependencia de Composer: leer texto de un PDF a mano es un parser
 * entero, y la herramienta de sistema lleva veinte años haciéndolo bien.
 *
 * Se lee el PDF de la pasada de medida, que no es PDF/A: sale de Chromium tal
 * cual, con el texto donde Chromium lo dejó.
 */
class LectorPaginas
{
    /**
     * @return array{paginas: array<string, int>, total: int} Id de sección => página, y el total.
     *
     * @throws GeneracionFallida
     */
    public function paginas(string $pdf): array
    {
        $fichero = tempnam(sys_get_temp_dir(), 'statera-medida-');

        if ($fichero === false) {
            throw GeneracionFallida::porGotenberg('no se pudo crear el temporal de la pasada de medida.');
        }

        try {
            file_put_contents($fichero, $pdf);

            $resultado = Process::timeout(60)->run(['pdftotext', '-enc', 'UTF-8', $fichero, '-']);

            if ($resultado->failed()) {
                throw GeneracionFallida::porGotenberg('pdftotext no pudo leer la pasada de medida: '.trim($resultado->errorOutput()));
            }

            return self::desdeTexto($resultado->output());
        } finally {
            @unlink($fichero);
        }
    }

    /**
     * La cuenta, separada de la herramienta para poder probarla sin ella.
     *
     * `pdftotext` termina cada página con un salto de página (`\f`), así que el
     * último trozo va vacío y no es una página.
     *
     * @return array{paginas: array<string, int>, total: int}
     */
    public static function desdeTexto(string $texto): array
    {
        $trozos = explode("\f", $texto);

        if (end($trozos) === '') {
            array_pop($trozos);
        }

        $patron = '/'.str_replace('%s', '(s-\d+)', preg_quote(RenderizadorCuerpo::MARCADOR, '/')).'/';
        $paginas = [];

        foreach ($trozos as $i => $trozo) {
            preg_match_all($patron, $trozo, $encontrados);

            foreach ($encontrados[1] as $id) {
                // La primera aparición: una sección empieza donde aparece su marcador.
                $paginas[$id] ??= $i + 1;
            }
        }

        return ['paginas' => $paginas, 'total' => count($trozos)];
    }
}
