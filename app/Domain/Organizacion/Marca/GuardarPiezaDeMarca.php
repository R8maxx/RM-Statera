<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Marca;

use App\Domain\Organizacion\Models\Organizacion;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda una de las dos piezas de marca de la organización.
 *
 * Dos caminos, porque los formatos no se parecen en nada:
 *
 * - **Mapa de bits → PNG con GD.** PNG y no WebP, al revés que la foto de
 *   perfil: un logo es gráfico plano, y PNG es sin pérdida, conserva alfa y es
 *   la opción aburrida para algo que va a imprenta. Se escala por el lado mayor
 *   y **no se agranda**.
 * - **SVG → se sanea y se guarda tal cual.** GD no lo puede tocar y tampoco
 *   debe: su `viewBox` es lo que manda y el tamaño lo pone el CSS. Escalarlo
 *   sería justamente perder lo único que un SVG aporta.
 *
 * **No se recorta ni se recolorea nunca**, en ninguno de los dos caminos. Un
 * logo de cliente no es nuestro para retocarlo (`DESIGN.md` §2), y en un
 * documento firmado eso importa.
 *
 * El orden de escritura es el de `GuardarFotoPerfil`: el objeto viejo se borra
 * **después** de que la fila apunte al nuevo. Al revés, un fallo a mitad deja la
 * organización apuntando a algo que ya no está, y se descubre al generar un PDF.
 */
final readonly class GuardarPiezaDeMarca
{
    private const DISCO = 'adjuntos';

    /**
     * Tope del SVG **ya saneado**, no del que llega.
     *
     * El SVG acaba incrustado en base64 dentro del CSS del documento, así que su
     * peso se paga en cada generación y crece un tercio por el camino. 256 kB de
     * vector es un logo muy detallado; por encima de eso, lo que hay dentro no
     * es un logo.
     */
    private const TOPE_SVG = 262144;

    public function __invoke(Organizacion $organizacion, PiezaDeMarca $pieza, UploadedFile $fichero): void
    {
        $anterior = $organizacion->getAttribute($pieza->columna());

        [$contenido, $extension] = $this->normalizar($pieza, $fichero);

        $ruta = sprintf(
            'marca/%d/%s-%s.%s',
            $organizacion->id,
            $pieza->value,
            Str::ulid()->toBase32(),
            $extension,
        );

        Storage::disk(self::DISCO)->put($ruta, $contenido);

        $organizacion->forceFill([$pieza->columna() => $ruta])->save();

        if (is_string($anterior) && $anterior !== $ruta) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
    }

    /**
     * @return array{0: string, 1: string} Contenido y extensión
     */
    private function normalizar(PiezaDeMarca $pieza, UploadedFile $fichero): array
    {
        $bytes = (string) file_get_contents($fichero->getRealPath());

        return $this->esSvg($fichero)
            ? [$this->sanearSvg($bytes), 'svg']
            : [$this->rasterizar($pieza, $bytes), 'png'];
    }

    /**
     * Por el contenido y no sólo por la extensión.
     *
     * `mimes:svg` de Laravel mira el contenido, pero aquí lo que decide es qué
     * camino se toma: pasarle a GD un SVG porque alguien lo llamó `.png` muere
     * con un error que no explica nada.
     */
    private function esSvg(UploadedFile $fichero): bool
    {
        return in_array(
            $fichero->getClientMimeType(),
            ['image/svg+xml', 'text/xml', 'application/xml'],
            true,
        ) || mb_strtolower((string) $fichero->getClientOriginalExtension()) === 'svg';
    }

    /**
     * El SVG, con lo peligroso fuera.
     *
     * Lo hace `enshrined/svg-sanitize` y no código propio, que es la excepción
     * al «la frontera de seguridad se implementa a mano» del multi-tenancy: allí
     * la regla es del dominio y aquí es una lista blanca de XML que alguien
     * mantiene mejor que nosotros — entidades, `DOCTYPE`, espacios de nombres,
     * `xlink:href`, CSS embebido y handlers `on*`. Un XXE al parsear no se ve
     * venir leyendo el diff.
     *
     * **Es la segunda barrera y no la única**, y conviene saberlo: el SVG sólo
     * se pinta como `background-image` del CSS y como `<img>`, y en esos
     * contextos ningún navegador ejecuta scripts. El saneado protege del día que
     * alguien lo incruste en el DOM, y de que un fichero raro llegue a Chromium
     * durante la generación.
     */
    private function sanearSvg(string $bytes): string
    {
        $sanitizador = new Sanitizer;
        $sanitizador->removeRemoteReferences(true);

        $limpio = $sanitizador->sanitize($bytes);

        if ($limpio === false || trim($limpio) === '') {
            throw new SvgNoAdmitido('El SVG no se ha podido leer.');
        }

        if (strlen($limpio) > self::TOPE_SVG) {
            throw new SvgNoAdmitido('El SVG es demasiado pesado: son '.round(strlen($limpio) / 1024).' kB y el tope son 256 kB.');
        }

        return $limpio;
    }

    /** Escala por el lado mayor, conserva alfa y no agranda. */
    private function rasterizar(PiezaDeMarca $pieza, string $bytes): string
    {
        $origen = @imagecreatefromstring($bytes);

        if ($origen === false) {
            throw new RuntimeException('La imagen no se ha podido decodificar.');
        }

        try {
            $ancho = imagesx($origen);
            $alto = imagesy($origen);
            $escala = min(1, $pieza->lado() / max($ancho, $alto));

            if ($escala === 1.0) {
                // Ya cabe: se reescribe a PNG sin tocar un píxel.
                return $this->aPng($origen);
            }

            $destino = imagecreatetruecolor((int) round($ancho * $escala), (int) round($alto * $escala));

            // Sin esto un logo con transparencia sale sobre fondo negro:
            // `imagecreatetruecolor` no nace transparente.
            imagealphablending($destino, false);
            imagesavealpha($destino, true);

            imagecopyresampled(
                $destino, $origen,
                0, 0, 0, 0,
                imagesx($destino), imagesy($destino),
                $ancho, $alto,
            );

            try {
                return $this->aPng($destino);
            } finally {
                imagedestroy($destino);
            }
        } finally {
            imagedestroy($origen);
        }
    }

    private function aPng(\GdImage $imagen): string
    {
        imagesavealpha($imagen, true);

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }
}
