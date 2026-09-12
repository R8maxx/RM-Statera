<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

/**
 * Poda un cuerpo que llega de fuera y lo deja en el esquema.
 *
 * **El cliente no es de fiar, ni siquiera el nuestro.** El editor declara el
 * mismo esquema en TypeScript y eso ya impide escribir, pegar o arrastrar lo que
 * no está declarado; esto es la segunda vez que se comprueba, en el único sitio
 * donde la comprobación cuenta. La ruta se puede llamar con `curl`.
 *
 * Poda en vez de rechazar, y es una decisión: un cuerpo con un nodo raro casi
 * siempre es una versión del editor que va por delante o por detrás del
 * servidor, y devolver un 422 sobre un documento de trescientos kilobytes deja a
 * quien escribía sin forma de guardar nada. Lo que no se entiende se quita, se
 * guarda lo demás y `RenderizadorCuerpo` tampoco lo pintaría.
 *
 * Lo que **no** se poda aquí es la procedencia: de eso responde `GuardarCuerpo`,
 * que la recalcula contra su propia línea base. Un cliente que se declare a sí
 * mismo «no editado» no se sale con la suya.
 */
final class SanearCuerpo
{
    /**
     * @param  array<string, mixed>  $cuerpo
     * @return array<string, mixed>|null null si no queda nada aprovechable
     */
    public function __invoke(array $cuerpo): ?array
    {
        $saneado = $this->nodo($cuerpo);

        // La raíz tiene que ser un `doc`: cualquier otra cosa no es un documento.
        return $saneado !== null && ($saneado['type'] ?? null) === 'doc' ? $saneado : null;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return array<string, mixed>|null
     */
    private function nodo(array $nodo): ?array
    {
        $tipo = $nodo['type'] ?? null;

        if (! is_string($tipo) || ! EsquemaCuerpo::admite($tipo)) {
            return null;
        }

        if ($tipo === 'text') {
            $texto = $nodo['text'] ?? null;

            if (! is_string($texto) || $texto === '') {
                return null;
            }

            $limpio = ['type' => 'text', 'text' => $texto];
            $marcas = $this->marcas($nodo);

            if ($marcas !== []) {
                $limpio['marks'] = $marcas;
            }

            return $limpio;
        }

        $limpio = ['type' => $tipo];

        $atributos = $this->atributos($nodo, $tipo);

        if ($atributos !== []) {
            $limpio['attrs'] = $atributos;
        }

        $hijos = $this->hijos($nodo);

        if ($hijos !== []) {
            $limpio['content'] = $hijos;
        }

        return $limpio;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<array<string, mixed>>
     */
    private function hijos(array $nodo): array
    {
        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos)) {
            return [];
        }

        $limpios = [];

        foreach ($hijos as $hijo) {
            if (! is_array($hijo)) {
                continue;
            }

            $saneado = $this->nodo($hijo);

            if ($saneado !== null) {
                $limpios[] = $saneado;
            }
        }

        return $limpios;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return array<string, mixed>
     */
    private function atributos(array $nodo, string $tipo): array
    {
        $atributos = $nodo['attrs'] ?? [];

        if (! is_array($atributos)) {
            return [];
        }

        $admitidos = EsquemaCuerpo::atributos($tipo);
        $limpios = [];

        foreach ($admitidos as $nombre) {
            if (! array_key_exists($nombre, $atributos)) {
                continue;
            }

            $valor = $this->valor($tipo, $nombre, $atributos[$nombre]);

            // Un atributo nulo no se escribe: así dos cuerpos con el mismo
            // contenido tienen el mismo JSON y el diff entre versiones no se
            // llena de ruido.
            if ($valor !== null) {
                $limpios[$nombre] = $valor;
            }
        }

        return $limpios;
    }

    /**
     * El valor de un atributo, o null si no pasa su comprobación.
     */
    private function valor(string $tipo, string $nombre, mixed $valor): mixed
    {
        return match ($nombre) {
            'fuente' => is_string($valor) && EsquemaCuerpo::esFuente($valor) ? $valor : null,
            'huella' => is_string($valor) && preg_match('/^[0-9a-f]{8,64}$/', $valor) === 1 ? $valor : null,
            'editado' => $valor === true ? true : null,

            'clase' => is_string($valor) && array_key_exists($valor, $this->clases($tipo)) ? $valor : null,
            'estilo' => is_string($valor) && array_key_exists($valor, EsquemaCuerpo::ESTILOS) ? $valor : null,
            /*
             * El valor por defecto no se escribe. El editor devuelve `simple`
             * explícito porque ProseMirror materializa los atributos con su
             * valor de serie; escribirlo dejaría dos representaciones del mismo
             * bloque, y entonces un bloque calculado saldría distinto de su
             * propia línea base sin que nadie lo haya tocado.
             *
             * `tono` no entra aquí: el badge lo escribe siempre, en los dos
             * lados, así que no hay dos representaciones que igualar.
             */
            'variante' => is_string($valor) && $valor !== 'simple' && array_key_exists($valor, EsquemaCuerpo::VARIANTES_CAJA) ? $valor : null,
            'tono' => is_string($valor) && array_key_exists($valor, EsquemaCuerpo::TONOS_BADGE) ? $valor : null,

            'level' => is_int($valor) && in_array($valor, EsquemaCuerpo::NIVELES, true) ? $valor : null,
            'colspan', 'rowspan' => is_int($valor) && $valor > 1 && $valor <= 64 ? $valor : null,
            'ancho' => is_string($valor) && EsquemaCuerpo::anchoValido($valor) ? $valor : null,
            'scope' => in_array($valor, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $valor : null,

            'segmentos' => is_array($valor) ? $this->segmentos($valor) : null,

            // Textos cortos de un nodo con forma propia: la ficha y las cifras.
            'clave', 'valor', 'de', 'etiqueta' => is_string($valor) && mb_strlen($valor) <= 200 ? $valor : null,

            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function clases(string $tipo): array
    {
        return match ($tipo) {
            'heading' => EsquemaCuerpo::CLASES_ENCABEZADO,
            'table' => EsquemaCuerpo::CLASES_TABLA,
            'tableRow' => EsquemaCuerpo::CLASES_FILA,
            'tableCell' => EsquemaCuerpo::CLASES_CELDA,
            default => EsquemaCuerpo::CLASES_PARRAFO,
        };
    }

    /**
     * El reparto de la gráfica. La clave decide el color por `var(--estado-…)`.
     *
     * @param  array<mixed>  $segmentos
     * @return list<array{clave: string, etiqueta: string, valor: int}>
     */
    private function segmentos(array $segmentos): array
    {
        $limpios = [];

        foreach ($segmentos as $segmento) {
            if (! is_array($segmento)) {
                continue;
            }

            $clave = $segmento['clave'] ?? null;
            $etiqueta = $segmento['etiqueta'] ?? null;
            $valor = $segmento['valor'] ?? null;

            if (! is_string($clave) || ! array_key_exists($clave, EsquemaCuerpo::TONOS_BADGE)) {
                continue;
            }

            if (! is_string($etiqueta) || ! is_int($valor) || $valor < 0) {
                continue;
            }

            $limpios[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'valor' => $valor];
        }

        return $limpios;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<array<string, mixed>>
     */
    private function marcas(array $nodo): array
    {
        $marcas = $nodo['marks'] ?? [];

        if (! is_array($marcas)) {
            return [];
        }

        $limpias = [];

        foreach ($marcas as $marca) {
            if (! is_array($marca)) {
                continue;
            }

            $tipo = $marca['type'] ?? null;

            if (! is_string($tipo) || ! EsquemaCuerpo::admiteMarca($tipo)) {
                continue;
            }

            if ($tipo !== 'link') {
                $limpias[] = ['type' => $tipo];

                continue;
            }

            $href = $marca['attrs']['href'] ?? null;

            // Un enlace que no se puede pintar deja el texto y pierde la
            // navegación, igual que en el renderizador.
            if (is_string($href) && EsquemaCuerpo::enlaceSeguro($href)) {
                $limpias[] = ['type' => 'link', 'attrs' => ['href' => $href]];
            }
        }

        return $limpias;
    }
}
