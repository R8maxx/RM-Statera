<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

/**
 * La hoja: tamaño y márgenes, en pulgadas.
 *
 * Estas cinco medidas estaban dentro de `GotenbergHttp` como constantes
 * privadas, que es donde nacieron y donde se usan de verdad. Salen de ahí porque
 * ahora hay **dos** sitios que tienen que estar de acuerdo sobre cómo es la
 * hoja: lo que Gotenberg imprime y lo que el editor pinta debajo del cursor. Con
 * las medidas escritas dos veces, el día que la hoja cambiara de orientación
 * —y cambió, de apaisada a vertical— el editor seguiría enseñando la anterior y
 * nadie lo notaría hasta abrir el PDF.
 *
 * Es el mismo criterio que ya rige en el panel: cada cifra se cuenta con el
 * alcance de lo que enseña al lado.
 *
 * En pulgadas y como cadenas porque es la unidad y el tipo que espera el cliente
 * de Gotenberg. Al editor viajan como números, que es lo que espera el CSS.
 */
final readonly class GeometriaPagina
{
    /**
     * A4 vertical.
     *
     * Fue apaisado mientras la tabla de la SoA tenía diez columnas en una sola
     * línea. Pasó a vertical cuando esa tabla pasó a dos líneas por requisito
     * —lo principal arriba, justificación, evidencia y correspondencias debajo—,
     * y no por gusto: **mezclar orientaciones en un mismo PDF no sobrevive al
     * PDF/A**. Chromium sí imprime páginas de dos tamaños, pero la conversión a
     * PDF/A-3b de Gotenberg pasa por LibreOffice, que impone el tamaño de la
     * primera página a todas: las apaisadas salían en vertical con la mitad
     * derecha cortada. Comprobado con `pdfinfo` en el 8.9.1.
     */
    public const ANCHO = '8.27';

    public const ALTO = '11.7';

    /** Arriba y abajo hay que dejar hueco para la cabecera y el pie, o no se pintan. */
    public const MARGEN_SUPERIOR = '0.87';

    public const MARGEN_INFERIOR = '0.71';

    public const MARGEN_LATERAL = '0.71';

    /** El ancho de la caja de texto: la hoja menos sus dos márgenes laterales. */
    public static function anchoUtil(): float
    {
        return (float) self::ANCHO - 2 * (float) self::MARGEN_LATERAL;
    }

    /**
     * Lo que necesita el editor para dibujar la misma hoja.
     *
     * @return array{ancho: float, alto: float, margenSuperior: float, margenInferior: float, margenLateral: float}
     */
    public static function paraElEditor(): array
    {
        return [
            'ancho' => (float) self::ANCHO,
            'alto' => (float) self::ALTO,
            'margenSuperior' => (float) self::MARGEN_SUPERIOR,
            'margenInferior' => (float) self::MARGEN_INFERIOR,
            'margenLateral' => (float) self::MARGEN_LATERAL,
        ];
    }
}
