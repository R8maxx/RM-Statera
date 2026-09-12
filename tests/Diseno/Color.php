<?php

declare(strict_types=1);

namespace Tests\Diseno;

/**
 * Conversión de color y las dos medidas que DESIGN.md exige.
 *
 * Existe porque el documento dice «las cifras salen de ejecutar el validador de
 * paletas sobre los hex de esta tabla, no de estimarlas» y **ese validador no
 * existía**: las cifras estaban escritas y no había forma de comprobarlas. Esto
 * es el validador, y `tests/Unit/Diseno/PaletaTest.php` lo ejecuta.
 *
 * **Vive en `tests/` y no en `app/`** a propósito: el producto no lo ejecuta
 * nunca —los colores los resuelve el navegador desde `app.css`— y meter en la
 * aplicación código que sólo corre en la suite es engordar lo que se despliega
 * con algo que no se usa.
 */
final class Color
{
    /**
     * De `oklch` a sRGB lineal.
     *
     * @return array{float, float, float}
     */
    public static function aLineal(float $l, float $c, float $h): array
    {
        [$L, $a, $b] = self::aOklab($l, $c, $h);

        $lp = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $mp = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $sp = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $lp - 3.3077115913 * $mp + 0.2309699292 * $sp,
            -1.2684380046 * $lp + 2.6097574011 * $mp - 0.3413193965 * $sp,
            -0.0041960863 * $lp - 0.7034186147 * $mp + 1.7076147010 * $sp,
        ];
    }

    /**
     * El hex sRGB, que es lo que va escrito en la tabla de DESIGN.md §3.
     *
     * @param  array{float, float, float}  $lineal
     */
    public static function aHex(array $lineal): string
    {
        $canales = array_map(static function (float $v): int {
            $v = max(0.0, min(1.0, $v));
            $s = $v <= 0.0031308 ? 12.92 * $v : 1.055 * ($v ** (1 / 2.4)) - 0.055;

            return (int) round($s * 255);
        }, $lineal);

        return sprintf('#%02X%02X%02X', ...$canales);
    }

    /**
     * El contraste de WCAG entre dos colores.
     *
     * @param  array{float, float, float}  $uno
     * @param  array{float, float, float}  $otro
     */
    public static function contraste(array $uno, array $otro): float
    {
        $a = self::luminancia($uno);
        $b = self::luminancia($otro);

        [$alto, $bajo] = $a > $b ? [$a, $b] : [$b, $a];

        return ($alto + 0.05) / ($bajo + 0.05);
    }

    /**
     * La distancia entre dos colores **tal y como los ve alguien con
     * protanopía**, que es la cifra que DESIGN.md §3 usa para el par
     * `implantado` / `en_progreso`.
     *
     * Simulación de Viénot, Brettel y Mollon (1999) sobre sRGB lineal, y luego
     * distancia euclídea en OKLab por cien, que es la escala en la que están
     * escritas las cifras del documento. Reproduce el 5.7 que el documento
     * anotaba para la paleta anterior.
     *
     * @param  array{float, float, float}  $uno
     * @param  array{float, float, float}  $otro
     */
    public static function distanciaConProtanopia(array $uno, array $otro): float
    {
        return self::distancia(
            self::aOklabDesdeLineal(self::protanopia($uno)),
            self::aOklabDesdeLineal(self::protanopia($otro)),
        );
    }

    /**
     * @return array{float, float, float}
     */
    private static function aOklab(float $l, float $c, float $h): array
    {
        $radianes = deg2rad($h);

        return [$l, $c * cos($radianes), $c * sin($radianes)];
    }

    /**
     * @param  array{float, float, float}  $lineal
     * @return array{float, float, float}
     */
    private static function aOklabDesdeLineal(array $lineal): array
    {
        [$r, $g, $b] = array_map(static fn (float $v): float => max(0.0, min(1.0, $v)), $lineal);

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    /**
     * @param  array{float, float, float}  $lineal
     * @return array{float, float, float}
     */
    private static function protanopia(array $lineal): array
    {
        [$r, $g, $b] = $lineal;

        return [
            0.11238 * $r + 0.88762 * $g,
            0.11238 * $r + 0.88762 * $g,
            0.00401 * $r - 0.00401 * $g + $b,
        ];
    }

    /**
     * @param  array{float, float, float}  $uno
     * @param  array{float, float, float}  $otro
     */
    private static function distancia(array $uno, array $otro): float
    {
        return sqrt(
            ($uno[0] - $otro[0]) ** 2 + ($uno[1] - $otro[1]) ** 2 + ($uno[2] - $otro[2]) ** 2
        ) * 100;
    }

    /**
     * @param  array{float, float, float}  $lineal
     */
    private static function luminancia(array $lineal): float
    {
        [$r, $g, $b] = array_map(static fn (float $v): float => max(0.0, min(1.0, $v)), $lineal);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
