<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Marca;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Facades\Storage;

/**
 * Quita una pieza de marca y **borra también el objeto del almacén**.
 *
 * Mismo criterio que `BorrarAdjunto` y que la foto de perfil, y aquí sin la
 * duda que había allí: un logo retirado no tiene por qué seguir en el bucket, y
 * las versiones ya emitidas no se ven afectadas porque su PDF está renderizado
 * y almacenado — el logo no se vuelve a leer para enseñarlas.
 *
 * Primero la fila y después el objeto, como en toda esta familia.
 */
final readonly class BorrarPiezaDeMarca
{
    private const DISCO = 'adjuntos';

    public function __invoke(Organizacion $organizacion, PiezaDeMarca $pieza): void
    {
        $ruta = $organizacion->getAttribute($pieza->columna());

        if (! is_string($ruta) || $ruta === '') {
            return;
        }

        $organizacion->forceFill([$pieza->columna() => null])->save();

        Storage::disk(self::DISCO)->delete($ruta);
    }
}
