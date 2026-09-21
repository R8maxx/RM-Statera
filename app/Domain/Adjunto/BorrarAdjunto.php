<?php

declare(strict_types=1);

namespace App\Domain\Adjunto;

use App\Domain\Adjunto\Models\Adjunto;
use Illuminate\Support\Facades\Storage;

/**
 * Borra un adjunto, y **borra también el objeto del almacén**.
 *
 * Es la diferencia deliberada con `EvidenciaController::destroy()`, que deja el
 * fichero en el bucket a propósito: allí hay Object Lock y valor probatorio, y
 * una evidencia borrada por error tiene que poder recuperarse.
 *
 * Aquí es al revés. Un adjunto puede contener **datos personales** —un DNI
 * escaneado, un contrato— y conservar lo que alguien borró sería el fallo, no la
 * garantía: quien ejerce su derecho de supresión no acepta «la fila ya no está».
 *
 * El fichero se borra **después** de la fila y no antes: si el `delete()` de la
 * base fallara, quedaría una fila apuntando a un objeto que ya no existe, y eso
 * se descubre al intentar descargarlo semanas más tarde.
 */
final readonly class BorrarAdjunto
{
    public function __invoke(Adjunto $adjunto): void
    {
        $disco = $adjunto->disco;
        $ruta = $adjunto->ruta;

        // Las pivotes se van en cascada; los anfitriones no se tocan.
        $adjunto->delete();

        Storage::disk($disco)->delete($ruta);
    }
}
