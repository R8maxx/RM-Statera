<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

/**
 * Constructores de nodos de ProseMirror.
 *
 * El JSON de ProseMirror escrito a mano es ilegible —tres niveles de `type`,
 * `attrs` y `content` para pintar un párrafo— y `CuerpoDeFabrica` y
 * `MaterializarCuerpo` no hacen otra cosa. Esto es azúcar, no dominio: aquí no
 * se decide nada, sólo se escribe con menos ruido.
 *
 * Los atributos nulos se podan al construir, para que dos cuerpos con el mismo
 * contenido tengan el mismo JSON: de eso depende que la huella de un bloque sea
 * comparable y que el diff entre versiones no esté lleno de ruido.
 *
 * @phpstan-type NodoArray array<string, mixed>
 */
final class Nodo
{
    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<array<string, mixed>>  $hijos
     * @return array<string, mixed>
     */
    public static function de(string $tipo, array $atributos = [], array $hijos = []): array
    {
        $nodo = ['type' => $tipo];

        $atributos = array_filter($atributos, static fn (mixed $valor): bool => $valor !== null);

        if ($atributos !== []) {
            $nodo['attrs'] = $atributos;
        }

        if ($hijos !== []) {
            $nodo['content'] = $hijos;
        }

        return $nodo;
    }

    /**
     * Texto llano, con las marcas que se le pasen.
     *
     * @param  list<string>  $marcas
     * @return array<string, mixed>
     */
    public static function texto(string $texto, array $marcas = []): array
    {
        $nodo = ['type' => 'text', 'text' => $texto];

        if ($marcas !== []) {
            $nodo['marks'] = array_map(
                static fn (string $marca): array => ['type' => $marca],
                $marcas,
            );
        }

        return $nodo;
    }

    /** @return array<string, mixed> */
    public static function enlace(string $texto, string $href): array
    {
        return [
            'type' => 'text',
            'text' => $texto,
            'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]],
        ];
    }

    /**
     * Un párrafo de texto llano. `$clase` es una clave de `CLASES_PARRAFO`.
     *
     * @return array<string, mixed>
     */
    public static function parrafo(string $texto, ?string $clase = null): array
    {
        return self::de('paragraph', ['clase' => $clase], [self::texto($texto)]);
    }

    /**
     * Un párrafo con el realce mínimo: `**negrita**` y `` `cifra` ``.
     *
     * Es la misma convención de `ContenidoDocumento::realce()`, y existe por lo
     * mismo: los textos de fábrica, las limitaciones y las notas del Anexo II se
     * escribieron con negritas y códigos de medida dentro, y transcribirlos a
     * nodos a mano, trozo a trozo, es donde se pierde uno. El acento grave
     * importa especialmente: cuando no se trataba, salía **impreso** en el PDF
     * que se le entregaba al auditor.
     *
     * @return array<string, mixed>
     */
    public static function parrafoRico(string $texto, ?string $clase = null): array
    {
        return self::de('paragraph', ['clase' => $clase], self::enLinea($texto));
    }

    /**
     * El realce mínimo, como nodos en línea.
     *
     * @return list<array<string, mixed>>
     */
    public static function enLinea(string $texto): array
    {
        $trozos = preg_split(
            '/(\*\*[^*]+\*\*|`[^`]+`)/u',
            $texto,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        return array_map(
            static fn (string $trozo): array => match (true) {
                str_starts_with($trozo, '**') => self::texto(mb_substr($trozo, 2, -2), ['bold']),
                str_starts_with($trozo, '`') => self::texto(mb_substr($trozo, 1, -1), ['cifra']),
                default => self::texto($trozo),
            },
            $trozos === false ? [$texto] : $trozos,
        );
    }

    /** @return array<string, mixed> */
    public static function encabezado(int $nivel, string $texto, ?string $clase = null, ?string $estilo = null): array
    {
        return self::de(
            'heading',
            ['level' => $nivel, 'clase' => $clase, 'estilo' => $estilo],
            [self::texto($texto)],
        );
    }

    /**
     * Un bloque calculado, marcado con el generador que lo produjo.
     *
     * @param  list<array<string, mixed>>  $hijos
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    public static function calculado(string $fuente, string $tipo, array $hijos = [], array $atributos = []): array
    {
        return self::de($tipo, [...$atributos, 'fuente' => $fuente], $hijos);
    }

    /**
     * Un hueco calculado todavía sin materializar.
     *
     * El esqueleto de fábrica lleva estos huecos para fijar **el orden** del
     * documento; `MaterializarCuerpo` los sustituye por el contenido del
     * registro. Un hueco sin materializar no se pinta.
     *
     * @return array<string, mixed>
     */
    public static function hueco(string $fuente): array
    {
        return self::de('grupo', ['fuente' => $fuente]);
    }

    /** @return array<string, mixed> */
    public static function badge(string $tono, string $texto): array
    {
        return self::de('badge', ['tono' => $tono], [self::texto($texto)]);
    }

    /**
     * Una celda de datos, con su contenido ya como nodos en línea.
     *
     * @param  list<array<string, mixed>>  $enLinea
     * @return array<string, mixed>
     */
    public static function celda(array $enLinea, ?string $clase = null, ?int $colspan = null): array
    {
        return self::de('tableCell', ['clase' => $clase, 'colspan' => $colspan], $enLinea);
    }

    /** @return array<string, mixed> */
    public static function celdaTexto(string $texto, ?string $clase = null): array
    {
        return self::celda([self::texto($texto)], $clase);
    }

    /** @return array<string, mixed> */
    public static function cabeceraCelda(string $texto, ?string $ancho = null, ?string $scope = null, ?int $colspan = null): array
    {
        return self::de(
            'tableHeader',
            ['ancho' => $ancho, 'scope' => $scope, 'colspan' => $colspan],
            [self::texto($texto)],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $celdas
     * @return array<string, mixed>
     */
    public static function fila(array $celdas, ?string $clase = null): array
    {
        return self::de('tableRow', ['clase' => $clase], $celdas);
    }

    /**
     * @param  list<string>  $puntos  con el realce mínimo de `enLinea()`
     * @return array<string, mixed>
     */
    public static function lista(array $puntos): array
    {
        return self::de('bulletList', [], array_map(
            static fn (string $punto): array => self::de('listItem', [], [self::parrafoRico($punto)]),
            $puntos,
        ));
    }
}
