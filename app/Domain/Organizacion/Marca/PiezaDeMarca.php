<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Marca;

/**
 * Cuál de las dos piezas de marca, y qué se espera de cada una.
 *
 * **Dos y no una**, por el mismo reparto que el propio logotipo de Statera tiene
 * entre `completo` y `simbolo`: la cabecera de una página del PDF mide 8 pt de
 * alto y ahí un logo con el nombre dentro no se lee. Es lo que hace cualquier
 * manual de marca.
 */
enum PiezaDeMarca: string
{
    case Logo = 'logo';
    case Simbolo = 'simbolo';

    public function columna(): string
    {
        return match ($this) {
            self::Logo => 'logo_ruta',
            self::Simbolo => 'simbolo_ruta',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Logo => 'Logo horizontal',
            self::Simbolo => 'Símbolo',
        };
    }

    /**
     * El lado mayor al que se reduce un mapa de bits.
     *
     * El logo se imprime a media pulgada de alto en la portada, así que 1024 px
     * de ancho son del orden de 2000 ppp: sobra para imprenta y no merece la
     * pena guardar más. El símbolo sale a 8 pt y se queda en 512 cuadrado.
     *
     * **No se agranda nunca**: estirar un logo de 64 px hasta 1024 no añade
     * información, añade peso y bordes sucios.
     */
    public function lado(): int
    {
        return match ($this) {
            self::Logo => 1024,
            self::Simbolo => 512,
        };
    }

    /**
     * Qué se le pide a quien sube la pieza, dicho en la pantalla.
     *
     * Es una recomendación y no una validación: **no se recorta**. Un logo de
     * cliente no es nuestro para retocarlo —lo dice `DESIGN.md` §2—, así que si
     * el símbolo llega apaisado se escala por el alto y sale apaisado. Rechazar
     * por proporciones sería impedir subir el logo que la empresa tiene.
     */
    public function forma(): string
    {
        return match ($this) {
            self::Logo => 'Apaisado. Se escala por el alto, así que la proporción es la que tú le des.',
            self::Simbolo => 'Cuadrado o casi. Se pinta a 8 pt de alto en la cabecera de cada página.',
        };
    }
}
