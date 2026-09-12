<?php

declare(strict_types=1);

namespace Tests\Diseno;

/**
 * Los tokens de color declarados en `resources/css/app.css`, leídos de ahí.
 *
 * **Se leen del CSS y no se copian**: una copia se desincroniza del fichero que
 * de verdad pinta, y entonces el validador daría por buenas unas cifras que ya
 * no son las de la pantalla.
 */
final class Paleta
{
    private const RUTA = 'resources/css/app.css';

    /** Dónde empieza cada tema dentro del fichero. */
    private const TEMAS = [
        'claro' => ':root {',
        'oscuro' => '.dark {',
    ];

    /**
     * Los `--estado-*` de un tema, en `oklch`.
     *
     * @return array<string, array{float, float, float}>
     */
    public static function estados(string $tema): array
    {
        $bloque = self::bloque($tema);
        $tokens = [];

        preg_match_all(
            '/--(estado-[a-z-]+):\s*oklch\(([\d.]+)\s+([\d.]+)\s+([\d.]+)\)/',
            $bloque,
            $coincidencias,
            PREG_SET_ORDER,
        );

        foreach ($coincidencias as $token) {
            $tokens[$token[1]] = [(float) $token[2], (float) $token[3], (float) $token[4]];
        }

        return $tokens;
    }

    /**
     * Los pares texto/fondo de cada estado, ya emparejados.
     *
     * @return array<string, array{texto: array{float, float, float}, fondo: array{float, float, float}}>
     */
    public static function paresDeEstado(string $tema): array
    {
        $tokens = self::estados($tema);
        $pares = [];

        foreach ($tokens as $nombre => $valor) {
            if (str_ends_with($nombre, '-suave')) {
                continue;
            }

            $suave = $tokens[$nombre.'-suave'] ?? null;

            if ($suave !== null) {
                $pares[$nombre] = ['texto' => $valor, 'fondo' => $suave];
            }
        }

        return $pares;
    }

    private static function bloque(string $tema): string
    {
        $css = (string) file_get_contents(base_path(self::RUTA));
        $inicio = strpos($css, self::TEMAS[$tema]);

        if ($inicio === false) {
            throw new \RuntimeException("No se encuentra el bloque del tema [{$tema}] en ".self::RUTA);
        }

        $fin = strpos($css, "\n}", $inicio);

        return substr($css, $inicio, $fin === false ? null : $fin - $inicio);
    }
}
