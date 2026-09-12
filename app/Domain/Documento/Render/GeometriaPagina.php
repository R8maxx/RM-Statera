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
 * las medidas escritas dos veces, el día que un tipo de documento pase a
 * vertical el editor seguiría enseñando una hoja apaisada y nadie lo notaría
 * hasta abrir el PDF.
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
     * A4 apaisado.
     *
     * Apaisado porque la tabla de la SoA tiene diez columnas y varias llevan
     * justificaciones de tres líneas: en 210 mm no caben, y encogerlas hasta que
     * quepan produce un documento que el auditor no lee.
     */
    public const ANCHO = '11.7';

    public const ALTO = '8.27';

    /** Arriba y abajo hay que dejar hueco para la cabecera y el pie, o no se pintan. */
    public const MARGEN_SUPERIOR = '0.87';

    public const MARGEN_INFERIOR = '0.71';

    public const MARGEN_LATERAL = '0.71';

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
