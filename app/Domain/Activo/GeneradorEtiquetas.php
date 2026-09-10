<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Organizacion\Models\Organizacion;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Las etiquetas QR que se pegan en los activos físicos.
 *
 * **Qué codifica el QR y por qué.** La URL de la ficha del activo, no un
 * `AVANZA|AV-PC-0001`. Escanear una pegatina tiene que abrir la ficha en el
 * móvil: con el código en texto plano hay que memorizarlo, abrir la aplicación
 * y buscarlo, y a la tercera vez nadie escanea nada. La hoja de cálculo de la
 * que viene este módulo ya lo dejaba anotado — «si algún día tenéis un portal de
 * inventario, pon aquí la URL base» — y Statera es ese portal.
 *
 * **Quién lleva etiqueta.** Sólo los activos físicos y vigentes. Una instancia
 * EC2 y una suscripción de SaaS no tienen carcasa donde pegar nada, y un equipo
 * retirado no se etiqueta, se borra.
 *
 * **El SVG se genera en el servidor**, no con una librería de JavaScript en el
 * navegador. El día que la hoja pase por Gotenberg para archivarse en PDF/A no
 * debe ejecutarse JavaScript en el documento: un canvas entra como mapa de bits
 * y se lleva por delante el texto seleccionable. Mismo criterio que las
 * gráficas.
 */
final class GeneradorEtiquetas
{
    /**
     * 4 módulos de margen —la «zona tranquila» que exige la norma— y 240 px de
     * lado. El SVG escala solo; el tamaño sólo fija la proporción interna.
     */
    private const LADO = 240;

    private const MARGEN = 4;

    /**
     * El SVG del código, listo para incrustar.
     *
     * Sale sin declaración XML para que pueda ir dentro del HTML con
     * `v-html`: una etiqueta `<?xml ?>` a mitad de documento lo invalida.
     */
    public function svg(Activo $activo, Organizacion $organizacion): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::LADO, self::MARGEN),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($this->contenido($activo, $organizacion));

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }

    /**
     * La URL que se codifica.
     *
     * `url_base_etiquetas` de la organización manda sobre `APP_URL` porque una
     * etiqueta impresa dura años: si la aplicación vive tras un proxy o con un
     * dominio propio por cliente, apuntar a la URL de desarrollo obligaría a
     * reimprimir el parque entero.
     */
    public function contenido(Activo $activo, Organizacion $organizacion): string
    {
        $base = rtrim($organizacion->url_base_etiquetas ?? (string) config('app.url'), '/');

        return "{$base}/activos/{$activo->id}";
    }

    /**
     * Los activos que deben llevar etiqueta, de entre los que se le pasen.
     *
     * Filtra en PHP y no en la consulta a propósito: «ser físico» lo decide
     * `TipoActivo::esFisico()`, que es una regla del dominio y no una columna, y
     * duplicarla en un `whereIn` de tipos sería tener dos sitios donde cambiarla.
     *
     * @param  Collection<int, Activo>  $activos
     * @return Collection<int, Activo>
     */
    public function etiquetables(Collection $activos): Collection
    {
        return $activos->filter(static fn (Activo $activo): bool => $activo->llevaEtiqueta())->values();
    }
}
