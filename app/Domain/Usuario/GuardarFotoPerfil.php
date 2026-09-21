<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda la foto de perfil de una cuenta, normalizada.
 *
 * **Todo lo que entra sale igual**: un WebP cuadrado de 256 px como mucho, en el
 * disco `adjuntos`. Sin eso, quien sube una foto de doce megas hecha con el
 * móvil la hace descargar en cada pantalla del producto, porque el avatar vive
 * en el chrome y el chrome se pinta siempre.
 *
 * **Se recorta, no se deforma.** Un retrato apaisado escalado a 256×256 sin
 * recortar sale aplastado; se toma el cuadrado central y se escala eso.
 *
 * **Y no se agranda.** Estirar una imagen de 64 px hasta 256 no añade
 * información: añade peso y borrosidad. El lado de destino es el menor entre
 * `LADO` y el del cuadrado de origen.
 *
 * La ruta lleva la organización delante, igual que en `SubirAdjunto`, para que
 * un listado del bucket sea legible y una política de S3 pueda acotarse por
 * prefijo. El aislamiento de verdad sigue estando en las tres capas de la base
 * — salvo que `users` es justamente el modelo que no las tiene, y por eso la
 * ruta que sirve la foto no admite decir de quién es.
 *
 * **La orientación EXIF no se corrige**, y queda dicho: la extensión `exif` no
 * está en la imagen y añadirla es tocar el `Dockerfile`. Una foto hecha de lado
 * con el móvil se guarda de lado. Se gira antes de subirla.
 */
final readonly class GuardarFotoPerfil
{
    private const DISCO = 'adjuntos';

    /** El lado del cuadrado que se guarda. Un avatar se pinta a 24–40 px. */
    private const LADO = 256;

    /** WebP con pérdida: a este tamaño la diferencia con 100 no se ve y pesa un tercio. */
    private const CALIDAD = 82;

    public function __invoke(User $usuario, UploadedFile $fichero): void
    {
        $anterior = $usuario->foto_ruta;

        $ruta = sprintf(
            'avatares/%s/%s.webp',
            $usuario->organizacion_id ?? 'sin-organizacion',
            Str::ulid()->toBase32(),
        );

        Storage::disk(self::DISCO)->put($ruta, $this->normalizar($fichero));

        $usuario->forceFill(['foto_ruta' => $ruta])->save();

        /*
         * El objeto viejo se borra DESPUÉS de que la fila apunte al nuevo, y no
         * antes: si la escritura fallara a mitad, la cuenta se quedaría
         * apuntando a un objeto que ya no existe y eso se descubre semanas más
         * tarde, al abrir cualquier pantalla. Mismo orden que en
         * `BorrarAdjunto`, y por el mismo motivo.
         */
        if ($anterior !== null && $anterior !== $ruta) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
    }

    /** El cuadrado central, escalado a lo sumo a `LADO`, en WebP. */
    private function normalizar(UploadedFile $fichero): string
    {
        $origen = @imagecreatefromstring((string) file_get_contents($fichero->getRealPath()));

        if ($origen === false) {
            // El `FormRequest` ya exige `image`, así que llegar aquí significa
            // que GD no sabe leer algo que Laravel sí reconoció. Es un 500
            // legítimo y no un error de quien lo sube.
            throw new RuntimeException('La imagen no se ha podido decodificar.');
        }

        try {
            $lado = min(imagesx($origen), imagesy($origen));
            $destino = min(self::LADO, $lado);

            $recorte = imagecreatetruecolor($destino, $destino);

            // Sin esto, un PNG o un WebP con transparencia sale con el fondo
            // negro: `imagecreatetruecolor` no nace transparente.
            imagealphablending($recorte, false);
            imagesavealpha($recorte, true);

            imagecopyresampled(
                $recorte,
                $origen,
                0, 0,
                intdiv(imagesx($origen) - $lado, 2),
                intdiv(imagesy($origen) - $lado, 2),
                $destino, $destino,
                $lado, $lado,
            );

            ob_start();
            imagewebp($recorte, null, self::CALIDAD);
            $bytes = (string) ob_get_clean();

            imagedestroy($recorte);

            return $bytes;
        } finally {
            imagedestroy($origen);
        }
    }
}
