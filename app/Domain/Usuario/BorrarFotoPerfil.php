<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Quita la foto de perfil, y **borra también el objeto del almacén**.
 *
 * Es el mismo criterio que `BorrarAdjunto` y el contrario que
 * `EvidenciaController::destroy()`, que deja el fichero a propósito porque allí
 * hay Object Lock y valor probatorio. Una cara no prueba nada y es un dato
 * personal: conservar lo que alguien borró sería el fallo, no la garantía.
 *
 * Primero la fila y después el objeto, como en los adjuntos: al revés, un fallo
 * a mitad deja la cuenta apuntando a algo que ya no está.
 */
final readonly class BorrarFotoPerfil
{
    private const DISCO = 'adjuntos';

    public function __invoke(User $usuario): void
    {
        $ruta = $usuario->foto_ruta;

        if ($ruta === null) {
            return;
        }

        $usuario->forceFill(['foto_ruta' => null])->save();

        Storage::disk(self::DISCO)->delete($ruta);
    }
}
