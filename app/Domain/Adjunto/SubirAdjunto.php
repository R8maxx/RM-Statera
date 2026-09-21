<?php

declare(strict_types=1);

namespace App\Domain\Adjunto;

use App\Domain\Adjunto\Concerns\ConAdjuntos;
use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sube un fichero y lo cuelga de un registro.
 *
 * El núcleo está copiado de `RegistrarEvidencia::guardar()`, incluidas las dos
 * cosas que ahí ya costaron:
 *
 * 1. **La huella se calcula del fichero recibido y ANTES de subirlo.** Se mide
 *    lo que llegó, no lo que quedó en el bucket.
 * 2. **El nombre en el almacén es un ULID**, y el nombre original se guarda en
 *    la fila para poder devolvérselo a quien lo descargue: el del bucket no le
 *    dice nada a nadie.
 *
 * La ruta lleva la organización delante para que un listado del bucket sea
 * legible y para que una política de S3 pueda acotarse por prefijo; el
 * aislamiento de verdad sigue estando en las tres capas de la base, no en la
 * forma del nombre.
 */
final readonly class SubirAdjunto
{
    private const DISCO = 'adjuntos';

    public function __construct(private ContextoOrganizacion $contexto) {}

    /**
     * Sube el fichero y lo vincula al anfitrión.
     *
     * El vínculo va en la misma llamada y no en dos pasos a propósito: un
     * adjunto sin anfitrión no lo enseña ninguna pantalla, así que dejar la
     * puerta abierta a crearlo suelto es dejar la puerta abierta a huérfanos.
     */
    public function __invoke(
        Model&ConAdjuntos $anfitrion,
        UploadedFile $fichero,
        string $titulo,
        ?string $nota = null,
        ?User $subidoPor = null,
    ): Adjunto {
        $adjunto = Adjunto::query()->create([
            ...$this->guardar($fichero),
            'titulo' => $titulo,
            'nota' => $nota,
            'subido_por_id' => $subidoPor?->id,
        ]);

        $anfitrion->adjuntos()->attach($adjunto->id, [
            'organizacion_id' => $adjunto->organizacion_id,
        ]);

        return $adjunto;
    }

    /**
     * @return array{disco: string, ruta: string, nombre_fichero: string, mime: string, tamano: int, hash_sha256: string}
     */
    private function guardar(UploadedFile $fichero): array
    {
        $huella = hash_file('sha256', $fichero->getRealPath());

        $extension = $fichero->getClientOriginalExtension();
        $nombre = Str::ulid()->toBase32().($extension === '' ? '' : ".{$extension}");
        $ruta = sprintf('%d/%s/%s', $this->contexto->idObligatorio(), Carbon::now()->format('Y'), $nombre);

        Storage::disk(self::DISCO)->putFileAs(dirname($ruta), $fichero, basename($ruta));

        return [
            'disco' => self::DISCO,
            'ruta' => $ruta,
            'nombre_fichero' => $fichero->getClientOriginalName(),
            'mime' => $fichero->getClientMimeType(),
            'tamano' => (int) $fichero->getSize(),
            'hash_sha256' => (string) $huella,
        ];
    }
}
