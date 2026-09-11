<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * El documento en `.docx`, para quien necesite pegarlo en un informe propio.
 *
 * **No es el entregable.** El entregable archivable es el PDF/A-3b con su huella
 * SHA-256, y esto es una copia de trabajo: lo dicen el pie de cada página, las
 * propiedades del fichero y su propio nombre. Van tres marcas porque la que se
 * olvida es siempre la única que había.
 *
 * **Se construye desde `ContenidoDocumento`, igual que la plantilla Blade.** Lo
 * que se duplica es la maquetación, no el contenido, y eso es inevitable: son
 * dos medios distintos. Lo que NO se hace es volver a consultar la base — el
 * contenido viene rehidratado de `instantanea`, o el Word de una versión de
 * marzo enseñaría los datos de octubre.
 *
 * Las tablas no pasan por el importador de HTML de PHPWord: su control de
 * anchos y de `colspan` es pobre y ahí es donde da sorpresas. La narrativa sí,
 * porque es el HTML ya saneado y son siete etiquetas.
 */
final readonly class EscritorWord
{
    private const ANCHO_UTIL = 9500; // Twips, A4 apaisado menos márgenes.

    /**
     * Las seis columnas del resumen.
     *
     * Entero y con `intdiv`: un ancho fraccionario sale al XML como
     * `w:w="1583.3333333333333"`, y los twips de OOXML son enteros.
     */
    private const ANCHO_RESUMEN = 1583;

    public function __invoke(Documento $documento, DocumentoVersion $version, ContenidoDocumento $contenido): string
    {
        $word = new PhpWord;

        $this->propiedades($word, $documento, $version, $contenido);
        $this->estilos($word);

        // A4 apaisado, como el PDF. Los twips se redondean a entero: `cmToTwip`
        // devuelve decimales y OOXML los quiere enteros.
        $margen = $this->twips(1.8);

        $seccion = $word->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => $this->twips(29.7),
            'pageSizeH' => $this->twips(21),
            'marginTop' => $margen,
            'marginBottom' => $margen,
            'marginLeft' => $margen,
            'marginRight' => $margen,
        ]);

        $this->pie($seccion, $documento, $version);

        $this->portada($seccion, $contenido);
        $this->narrativa($seccion, $contenido, $contenido->textos->aperturas());
        $this->resumen($seccion, $contenido);
        $this->tabla($seccion, $contenido);
        $this->narrativa($seccion, $contenido, $contenido->textos->cierres());
        $this->limitaciones($seccion, $contenido);

        return $this->aCadena($word);
    }

    private function twips(float $centimetros): int
    {
        return (int) round(Converter::cmToTwip($centimetros));
    }

    /**
     * Las propiedades del fichero.
     *
     * Sobreviven a copiarlo y a renombrarlo, que es justo lo que le pasa a un
     * documento que circula por correo. La huella del PDF va aquí para que
     * siempre se pueda saber a qué entrega corresponde esta copia.
     */
    private function propiedades(PhpWord $word, Documento $documento, DocumentoVersion $version, ContenidoDocumento $contenido): void
    {
        $info = $word->getDocInfo();

        $info->setTitle($documento->titulo.' — '.$documento->codigo.' (copia de trabajo)');
        $info->setSubject($contenido->subtitulo);
        $info->setDescription(
            'Copia de trabajo. El entregable archivable es el PDF/A-3b de la versión '
            .$version->etiqueta().'.'
        );
        $info->setCategory('Copia de trabajo');
        $info->setCreator('Statera');

        $info->setCustomProperty('Statera-Entregable', 'PDF/A-3b '.$version->etiqueta());
        $info->setCustomProperty('Statera-Huella-PDF', (string) $version->hash_sha256);
    }

    private function estilos(PhpWord $word): void
    {
        $word->setDefaultFontName('Instrument Sans');
        $word->setDefaultFontSize(9);

        $word->addTitleStyle(1, ['size' => 20, 'bold' => true]);
        $word->addTitleStyle(2, ['size' => 13, 'bold' => true], ['spaceBefore' => 320, 'spaceAfter' => 120]);
        $word->addTitleStyle(3, ['size' => 10, 'bold' => true], ['spaceBefore' => 200, 'spaceAfter' => 80]);

        /*
         * La lista lleva estilo declarado. `addListItem` sin él acaba pidiendo
         * `Style::getStyle(null)` al guardar y PHP 8.4 avisa de obsolescencia;
         * además, una lista sin numeración declarada sale sin viñeta en Word.
         */
        $word->addNumberingStyle('vinetas', [
            'type' => 'singleLevel',
            'levels' => [[
                'format' => 'bullet',
                'text' => '',
                'alignment' => Jc::LEFT,
                'left' => 360,
                'hanging' => 360,
                'tabPos' => 360,
            ]],
        ]);

        $word->addTableStyle('tabla', [
            'borderColor' => 'DAE1E3',
            'borderSize' => 6,
            'cellMargin' => 60,
        ], ['bgColor' => 'ECF2F4']);
    }

    /**
     * El aviso va en el pie de TODAS las páginas, no sólo en la primera: un
     * documento se reenvía, se imprime suelto y se lee por la mitad.
     */
    private function pie(Section $seccion, Documento $documento, DocumentoVersion $version): void
    {
        $pie = $seccion->addFooter();

        $pie->addPreserveText(
            sprintf(
                '%s · %s · Copia de trabajo — el entregable es el PDF/A %s · Página {PAGE} de {NUMPAGES}',
                $documento->codigo,
                $documento->clasificacion->sello(),
                $version->etiqueta(),
            ),
            ['size' => 7, 'color' => '5D6C72'],
            ['alignment' => Jc::CENTER],
        );
    }

    private function portada(Section $seccion, ContenidoDocumento $contenido): void
    {
        $p = $contenido->portada;

        $seccion->addText('STATERA', ['size' => 8, 'bold' => true, 'color' => '007E81']);
        $seccion->addTitle($contenido->titulo, 1);
        $seccion->addText($contenido->subtitulo, ['size' => 11, 'color' => '5D6C72'], ['spaceAfter' => 240]);

        $ficha = $seccion->addTable('tabla');

        foreach ([
            'Organización' => $p['organizacion'] ?? '—',
            'Sistema' => trim(($p['sistemaCodigo'] ?? '').' · '.($p['sistemaNombre'] ?? ''), ' ·'),
            'Marco' => $p['marco'] ?? '—',
            'Documento' => ($p['documentoCodigo'] ?? '—').' · '.($p['version'] ?? '—'),
            'Fecha' => $p['fecha'] ?? '—',
            'Clasificación' => $p['clasificacion'] ?? '—',
            'Responsable' => $p['responsable'] ?? 'Sin asignar',
        ] as $clave => $valor) {
            $fila = $ficha->addRow();
            $fila->addCell(2200)->addText((string) $clave, ['size' => 8, 'color' => '5D6C72']);
            $fila->addCell(self::ANCHO_UTIL - 2200)->addText((string) $valor);
        }

        if (! empty($p['alcance'])) {
            $seccion->addTitle('Alcance declarado', 3);
            $seccion->addText((string) $p['alcance']);
        }
    }

    /**
     * @param  list<SeccionNarrativa>  $secciones
     */
    private function narrativa(Section $seccion, ContenidoDocumento $contenido, array $secciones): void
    {
        foreach ($secciones as $clave) {
            if (! $contenido->textos->tiene($clave)) {
                continue;
            }

            $seccion->addTitle($clave->titulo(), 2);
            $this->html($seccion, $contenido->textos->html($clave));
        }
    }

    private function resumen(Section $seccion, ContenidoDocumento $contenido): void
    {
        $r = $contenido->resumen;

        $seccion->addTitle('Resumen', 2);

        $tabla = $seccion->addTable('tabla');
        $cabecera = $tabla->addRow();

        foreach (['Requisitos', 'Aplicables', 'Excluidos', 'Implantados', 'Madurez media', 'Sin evidencia'] as $titulo) {
            $cabecera->addCell(self::ANCHO_RESUMEN)->addText($titulo, ['bold' => true, 'size' => 8]);
        }

        $fila = $tabla->addRow();

        // Cada cifra con su denominador, igual que en el PDF.
        foreach ([
            ($r['total'] ?? 0).' de '.($r['enElMarco'] ?? $r['total'] ?? 0),
            ($r['aplicables'] ?? 0).' de '.($r['total'] ?? 0),
            (string) ($r['excluidos'] ?? 0),
            ($r['implantados'] ?? 0).' de '.($r['aplicables'] ?? 0),
            $r['madurezMedia'] === null ? '— sobre '.($r['madurezEvaluadas'] ?? 0) : 'L'.$r['madurezMedia'].' sobre '.($r['madurezEvaluadas'] ?? 0),
            ($r['sinEvidencia'] ?? 0).' de '.($r['aplicables'] ?? 0),
        ] as $valor) {
            $fila->addCell(self::ANCHO_RESUMEN)->addText((string) $valor);
        }
    }

    private function tabla(Section $seccion, ContenidoDocumento $contenido): void
    {
        $seccion->addTitle($contenido->subtitulo, 2);

        $columnas = $this->columnas($contenido);

        $tabla = $seccion->addTable('tabla');

        // La cabecera se repite en cada página: una tabla de noventa y tres
        // filas sin ella deja de poder leerse a partir de la segunda.
        $cabecera = $tabla->addRow(null, ['tblHeader' => true]);

        foreach ($columnas as $titulo => $ancho) {
            $cabecera->addCell($ancho)->addText((string) $titulo, ['bold' => true, 'size' => 7]);
        }

        $grupo = null;

        foreach ($contenido->filas as $fila) {
            if ($fila->grupo !== $grupo) {
                $grupo = $fila->grupo;
                $tabla->addRow()
                    ->addCell(self::ANCHO_UTIL, ['gridSpan' => count($columnas), 'bgColor' => 'D5F4F4'])
                    ->addText($grupo, ['bold' => true, 'size' => 8, 'color' => '006467']);
            }

            $this->filaDeTabla($tabla->addRow(), $fila, $columnas, $contenido);
        }
    }

    /**
     * @return array<string, int>
     */
    private function columnas(ContenidoDocumento $contenido): array
    {
        $esEns = $contenido->filas !== [] && $contenido->filas[0]->exigencia !== null;

        return $esEns
            ? ['Medida' => 900, 'Título' => 2100, 'Exigencia' => 900, 'Origen' => 1400, 'Aplica' => 600, 'Estado' => 1000, 'Madurez' => 700, 'Evidencia' => 1900]
            : ['Control' => 800, 'Título' => 2200, 'Aplica' => 600, 'Origen de la inclusión' => 1900, 'Justificación de exclusión' => 1700, 'Estado' => 1000, 'Madurez' => 700, 'Evidencia' => 1600];
    }

    /**
     * @param  array<string, int>  $columnas
     */
    private function filaDeTabla(Row $fila, FilaRequisito $dato, array $columnas, ContenidoDocumento $contenido): void
    {
        $valores = [
            'Control' => $dato->codigo,
            'Medida' => $dato->codigo,
            'Título' => $dato->titulo,
            'Aplica' => $dato->aplica ? 'Sí' : 'No',
            'Exigencia' => $dato->exigencia ?? '—',
            'Origen' => trim(($dato->origenExigencia ?? '—').($dato->dimensionModuladora !== null ? ' ('.$dato->dimensionModuladora.')' : '')),
            'Origen de la inclusión' => $dato->justificacionInclusion ?? '—',
            'Justificación de exclusión' => $dato->justificacion ?? ($dato->aplica ? '—' : 'SIN JUSTIFICAR'),
            'Estado' => $dato->estadoEtiqueta,
            'Madurez' => $dato->madurez ?? '—',
            'Evidencia' => $dato->evidenciaODefecto(),
        ];

        foreach ($columnas as $titulo => $ancho) {
            $fila->addCell($ancho)->addText(
                (string) ($valores[$titulo] ?? '—'),
                ['size' => 7, 'bold' => $titulo === 'Aplica' && ! $dato->aplica],
            );
        }
    }

    private function limitaciones(Section $seccion, ContenidoDocumento $contenido): void
    {
        if ($contenido->limitaciones === []) {
            return;
        }

        $seccion->addTitle('Limitaciones de esta declaración', 2);

        foreach ($contenido->limitaciones as $limitacion) {
            // Se pinta en texto llano: el realce de `**` es del PDF y meterlo
            // aquí obligaría a partir cada frase en trozos con estilo.
            $seccion->addListItem($this->sinRealce($limitacion), 0, ['size' => 8], 'vinetas');
        }

        if ($contenido->textos->tiene('limitaciones_propias')) {
            $seccion->addTitle('Limitaciones declaradas por la organización', 3);
            $this->html($seccion, $contenido->textos->html('limitaciones_propias'));
        }
    }

    /** Quita el `**` y los acentos graves del realce del PDF. */
    private function sinRealce(string $texto): string
    {
        return str_replace(['**', '`'], '', $texto);
    }

    /**
     * Mete HTML ya saneado en el documento.
     *
     * `addHtml` de PHPWord acepta un subconjunto pequeño y se porta bien con las
     * siete etiquetas que produce `MarkdownDocumento`. Si algo se le atraganta,
     * se cae a texto llano antes que romper la descarga entera.
     */
    private function html(Section $seccion, string $html): void
    {
        if ($html === '') {
            return;
        }

        try {
            Html::addHtml($seccion, $html, false, false);
        } catch (\Throwable) {
            $seccion->addText(trim(strip_tags($html)));
        }
    }

    private function aCadena(PhpWord $word): string
    {
        $escritor = new Word2007($word);

        ob_start();
        $escritor->save('php://output');

        return (string) ob_get_clean();
    }
}
