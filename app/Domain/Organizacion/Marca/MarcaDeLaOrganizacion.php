<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Marca;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Las piezas de marca listas para incrustarse en un documento.
 *
 * **Data URI y no un fichero del multipart**, aunque base64 pese un tercio más,
 * y el motivo decide el diseño entero: Chromium renderiza la cabecera y el pie
 * en un contexto aparte que **no recibe los assets del multipart** —está escrito
 * en `cabecera.blade.php` y lo fija `GeneracionTest`—. Como el símbolo tiene que
 * ir sí o sí en línea, se usa la misma vía para el logo de la portada: un solo
 * mecanismo, un solo sitio donde se rompe. Y es la vía que las fuentes del
 * documento ya recorren desde hace tiempo.
 *
 * `data:` está en la allow-list de Gotenberg (`^(file:///tmp/|data:).*`), así que
 * esto pasa sin tocar la configuración del contenedor. Una URL remota no pasaría,
 * y con `failOnResourceLoadingFailed()` encendido tumbaría la generación entera.
 *
 * **Un fallo al leer el objeto no tumba el documento.** Si el almacén no
 * responde, la pieza se omite y el PDF sale como salía antes de que existieran
 * los logos — que es exactamente lo que hace falta: un logo es decoración y un
 * documento que no se genera es un problema de verdad.
 */
final readonly class MarcaDeLaOrganizacion
{
    private const DISCO = 'adjuntos';

    /** El `data:` de una pieza, o nulo si no hay o no se puede leer. */
    public function dataUri(Organizacion $organizacion, PiezaDeMarca $pieza): ?string
    {
        $ruta = $organizacion->getAttribute($pieza->columna());

        if (! is_string($ruta) || $ruta === '') {
            return null;
        }

        try {
            $bytes = Storage::disk(self::DISCO)->get($ruta);
        } catch (Throwable) {
            return null;
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        return sprintf('data:%s;base64,%s', self::mime($ruta), base64_encode($bytes));
    }

    /**
     * El tipo sale de la extensión y no de una columna.
     *
     * `GuardarPiezaDeMarca` sólo escribe dos: `.png` para todo mapa de bits y
     * `.svg` para el vector ya saneado. Guardar el mime en la tabla sería el
     * mismo dato en dos sitios que pueden discrepar.
     */
    private static function mime(string $ruta): string
    {
        return str_ends_with($ruta, '.svg') ? 'image/svg+xml' : 'image/png';
    }
}
