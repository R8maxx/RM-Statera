<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Domain\Documento\Cuerpo\RenderizadorCuerpo;

/**
 * El índice del PDF, con el número de página de cada sección.
 *
 * Chromium no sabe numerar un índice: no implementa `target-counter()` y no
 * expone la paginación a nadie. Así que se imprime **dos veces**: una pasada de
 * medida, sin PDF/A y con un marcador invisible al principio de cada sección
 * (`RenderizadorCuerpo::MARCADOR`), de la que se lee en qué página cayó cada
 * uno; y la definitiva, con los números ya escritos. El índice ocupa lo mismo en
 * las dos —los números de relleno tienen el ancho de los reales—, así que nada
 * se mueve entre una y otra.
 *
 * **No es un nodo del cuerpo.** Se deriva al imprimir, como la cabecera y el
 * pie: no entra en la instantánea ni en el editor, y no hay forma de que diga
 * algo distinto de lo que el documento contiene.
 *
 * Los números son los del pie, que cuenta desde la primera página después de la
 * portada: la portada se imprime aparte y no lleva número.
 *
 * Y no es pulsable en el PDF entregado: Chromium sí genera los enlaces, pero la
 * conversión a PDF/A de Gotenberg los descarta. Los marcadores laterales del
 * visor sí sobreviven, y son el índice navegable.
 *
 * @phpstan-type Entrada array{id: string, titulo: string}
 */
final class IndiceDocumento
{
    /** Con menos secciones no hay nada que buscar: el documento no lleva índice. */
    public const SECCIONES_MINIMAS = 3;

    /**
     * Y con estas páginas de contenido o menos, tampoco. Un índice en un
     * documento de cuatro páginas es una página más que pasar.
     */
    public const PAGINAS_MINIMAS = 4;

    /** El relleno de la pasada de medida: tres cifras, como el número más largo. */
    private const RELLENO = '000';

    /**
     * Una entrada por sección, con el texto de su primer encabezado de nivel 2.
     *
     * Cuenta las secciones igual que `RenderizadorCuerpo` —en orden, todas, las
     * que no tienen título incluidas— para que el `id` de cada entrada sea el de
     * su sección. Una sección sin `h2` no entra en el índice, pero consume su
     * número.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return list<Entrada>
     */
    public function entradas(array $cuerpo): array
    {
        $entradas = [];
        $n = 0;
        $hijos = is_array($cuerpo['content'] ?? null) ? $cuerpo['content'] : [];

        foreach ($hijos as $hijo) {
            if (! is_array($hijo) || ($hijo['type'] ?? null) !== 'seccion') {
                continue;
            }

            $id = RenderizadorCuerpo::idSeccion(++$n);
            $titulo = $this->primerTitulo($hijo);

            if ($titulo !== null) {
                $entradas[] = ['id' => $id, 'titulo' => $titulo];
            }
        }

        return $entradas;
    }

    /**
     * Si el documento tiene secciones suficientes como para medirlo.
     *
     * @param  list<Entrada>  $entradas
     */
    public function mereceMedirse(array $entradas): bool
    {
        return count($entradas) >= self::SECCIONES_MINIMAS;
    }

    /**
     * Si, ya medido, el documento es lo bastante largo como para llevar índice.
     *
     * Se cuentan las páginas **de contenido**: las del índice no, que son las
     * que preceden a la primera sección.
     *
     * @param  list<Entrada>  $entradas
     * @param  array{paginas: array<string, int>, total: int}  $medicion
     */
    public function mereceIndice(array $entradas, array $medicion): bool
    {
        if ($entradas === []) {
            return false;
        }

        $primera = $medicion['paginas'][$entradas[0]['id']] ?? null;

        if ($primera === null) {
            return false;
        }

        return $medicion['total'] - ($primera - 1) > self::PAGINAS_MINIMAS;
    }

    /**
     * El HTML del índice. Sin páginas, con el relleno de la pasada de medida.
     *
     * Una entrada cuyo marcador no apareciera en el PDF sale sin número antes que
     * con uno inventado.
     *
     * @param  list<Entrada>  $entradas
     * @param  array<string, int>|null  $paginas
     */
    public function html(array $entradas, ?array $paginas = null): string
    {
        $filas = '';

        foreach ($entradas as $entrada) {
            $pagina = $paginas === null
                ? self::RELLENO
                : (string) ($paginas[$entrada['id']] ?? '');

            $filas .= '<li class="indice__entrada">'
                .'<a class="indice__titulo" href="#'.e($entrada['id']).'">'.e($entrada['titulo']).'</a>'
                .'<span class="indice__guia" aria-hidden="true"></span>'
                .'<span class="indice__pagina cifra">'.e($pagina).'</span>'
                .'</li>';
        }

        return '<nav class="indice" aria-labelledby="indice-titulo">'
            .'<h2 id="indice-titulo">Índice</h2>'
            .'<ol class="indice__lista">'.$filas.'</ol>'
            .'</nav>';
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function primerTitulo(array $nodo): ?string
    {
        $hijos = is_array($nodo['content'] ?? null) ? $nodo['content'] : [];

        foreach ($hijos as $hijo) {
            if (! is_array($hijo)) {
                continue;
            }

            if (($hijo['type'] ?? null) === 'heading' && ($hijo['attrs']['level'] ?? null) === 2) {
                $texto = trim($this->texto($hijo));

                return $texto === '' ? null : $texto;
            }

            $dentro = $this->primerTitulo($hijo);

            if ($dentro !== null) {
                return $dentro;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function texto(array $nodo): string
    {
        if (($nodo['type'] ?? null) === 'text') {
            return is_string($nodo['text'] ?? null) ? $nodo['text'] : '';
        }

        $hijos = is_array($nodo['content'] ?? null) ? $nodo['content'] : [];

        return implode('', array_map(
            fn (mixed $hijo): string => is_array($hijo) ? $this->texto($hijo) : '',
            $hijos,
        ));
    }
}
