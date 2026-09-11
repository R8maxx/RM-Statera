<?php

declare(strict_types=1);

namespace App\Domain\Documento\Render;

use App\Http\Resources\Panel\SegmentoEstado;

/**
 * Las gráficas del documento, generadas en el servidor.
 *
 * **SVG escrito en PHP y sin una línea de JavaScript**, y el motivo es concreto:
 * en un documento que va a PDF/A-3b y aspira a PDF/UA no debe ejecutarse
 * JavaScript, porque un `canvas` entra como mapa de bits y se lleva por delante
 * el texto seleccionable. Es la misma razón por la que el QR de las etiquetas se
 * dibuja en el servidor y por la que no entró ninguna librería de gráficas.
 *
 * Los colores salen de las variables de `documento.css`, así que la paleta sigue
 * viviendo en un solo sitio: `fill="var(--estado-implantado)"` funciona dentro
 * de un SVG en línea en Chromium.
 */
final class GraficaSvg
{
    private const ANCHO = 700;

    private const ALTO = 18;

    /** Los tramos pegados se leen como una mancha; separados, no. */
    private const HUECO = 2;

    /**
     * La barra por tramos del reparto de estados.
     *
     * **El orden llega dado y no se reordena aquí.** `ResumenCumplimiento::porEstado()`
     * lo fija —implantado, planificado, en progreso, no iniciado— porque el verde
     * y el ámbar no se distinguen con protanopia (ΔE 5.7, por debajo del suelo de
     * 6) y meter el azul entre los dos sube la peor pareja contigua a 14.0.
     * Reordenar por tamaño rompería eso sin que se note en la pantalla de quien
     * lo hiciera.
     *
     * @param  list<SegmentoEstado>  $segmentos
     */
    public function barraPorEstado(array $segmentos): string
    {
        $total = array_sum(array_map(static fn (SegmentoEstado $s): int => $s->valor, $segmentos));

        if ($total === 0) {
            return '';
        }

        $visibles = array_values(array_filter(
            $segmentos,
            static fn (SegmentoEstado $s): bool => $s->valor > 0,
        ));

        $huecos = self::HUECO * max(0, count($visibles) - 1);
        $util = self::ANCHO - $huecos;

        $tramos = '';
        $x = 0.0;

        foreach ($visibles as $segmento) {
            $ancho = $util * $segmento->valor / $total;

            $tramos .= sprintf(
                '<rect x="%.2f" y="0" width="%.2f" height="%d" rx="2" fill="var(--estado-%s)"/>',
                $x,
                $ancho,
                self::ALTO,
                str_replace('_', '-', $segmento->clave),
            );

            $x += $ancho + self::HUECO;
        }

        return $this->envolver($tramos, $this->descripcion($visibles, $total));
    }

    /**
     * La alternativa textual, que es lo que oye quien no ve la barra.
     *
     * @param  list<SegmentoEstado>  $segmentos
     */
    private function descripcion(array $segmentos, int $total): string
    {
        $partes = array_map(
            // Siempre con el denominador: «41 implantado» no dice nada, «41 de
            // 93 implantado» sí.
            static fn (SegmentoEstado $s): string => "{$s->etiqueta}: {$s->valor} de {$total}",
            $segmentos,
        );

        return 'Reparto por estado. '.implode('. ', $partes).'.';
    }

    private function envolver(string $contenido, string $descripcion): string
    {
        $titulo = e($descripcion);

        return <<<SVG
            <svg class="grafica__barra" viewBox="0 0 700 18" width="100%" height="18"
                 role="img" aria-label="{$titulo}" xmlns="http://www.w3.org/2000/svg">
                <title>{$titulo}</title>
                {$contenido}
            </svg>
            SVG;
    }
}
