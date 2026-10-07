<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
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
 * **El contenido sale del cuerpo de la instantánea**, que es el mismo árbol que
 * se imprimió en el PDF: lo recorre `CuerpoAWord`. Antes esta clase montaba una
 * secuencia fija —portada, textos, resumen, tabla, limitaciones— y desde que el
 * documento se edita entero eso era otro documento distinto del que se entregó.
 *
 * Lo que sigue viviendo aquí es el continente: el tamaño de la hoja, los estilos,
 * el pie que marca la copia de trabajo y las propiedades del fichero.
 */
final readonly class EscritorWord
{
    /** Twips: los 17,4 cm del A4 vertical menos dos márgenes de 1,8 cm. */
    private const ANCHO_UTIL = 9865;

    public function __construct(private IndiceDocumento $indice = new IndiceDocumento) {}

    /**
     * @param  array<string, mixed>  $cuerpo  el árbol congelado en la instantánea
     */
    public function __invoke(Documento $documento, DocumentoVersion $version, ContenidoDocumento $contenido, array $cuerpo): string
    {
        $word = new PhpWord;

        $this->propiedades($word, $documento, $version, $contenido);
        $this->estilos($word);

        // A4 vertical, como el PDF. Los twips se redondean a entero: `cmToTwip`
        // devuelve decimales y OOXML los quiere enteros.
        $margen = $this->twips(1.8);

        $seccion = $word->addSection([
            'orientation' => 'portrait',
            'pageSizeW' => $this->twips(21),
            'pageSizeH' => $this->twips(29.7),
            'marginTop' => $margen,
            'marginBottom' => $margen,
            'marginLeft' => $margen,
            'marginRight' => $margen,
        ]);

        $this->pie($seccion, $documento, $version);

        $escritor = new CuerpoAWord(self::ANCHO_UTIL);
        ['portada' => $portada, 'resto' => $resto] = $this->partir($cuerpo);

        $escritor($seccion, $portada);

        /*
         * El índice, con el mismo umbral de secciones que el PDF. Aquí no hay
         * pasada de medida: es un campo de Word, que numera él mismo al abrir el
         * fichero —por eso `updateFields`, que hace que Word lo pida—. Desde el
         * nivel 2, que es el de las secciones: el nivel 1 es el título de la
         * portada.
         */
        if ($this->indice->mereceMedirse($this->indice->entradas($cuerpo))) {
            $word->getSettings()->setUpdateFields(true);

            $seccion->addPageBreak();
            $seccion->addText('Índice', ['size' => 13, 'bold' => true]);
            $seccion->addTOC(['size' => 9], null, 2, 2);
        }

        $seccion->addPageBreak();
        $escritor($seccion, $resto);

        return $this->aCadena($word);
    }

    /**
     * La portada por un lado y el resto por otro, como dos documentos.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return array{portada: array<string, mixed>, resto: array<string, mixed>}
     */
    private function partir(array $cuerpo): array
    {
        $portada = [];
        $resto = [];
        $hijos = is_array($cuerpo['content'] ?? null) ? $cuerpo['content'] : [];

        foreach ($hijos as $hijo) {
            if (is_array($hijo) && ($hijo['type'] ?? null) === 'portada') {
                $portada[] = $hijo;
            } else {
                $resto[] = $hijo;
            }
        }

        return [
            'portada' => ['type' => 'doc', 'content' => $portada],
            'resto' => ['type' => 'doc', 'content' => $resto],
        ];
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
        // El editor ofrece cuatro niveles; sin estilo declarado, `addTitle(…, 4)`
        // pide `Style::getStyle(null)` al guardar y PHP 8.4 avisa de obsolescencia.
        $word->addTitleStyle(4, ['size' => 9, 'bold' => true], ['spaceBefore' => 160, 'spaceAfter' => 60]);

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

    private function aCadena(PhpWord $word): string
    {
        $escritor = new Word2007($word);

        ob_start();
        $escritor->save('php://output');

        return (string) ob_get_clean();
    }
}
