<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Copia los objetos de evidencias, documentos y adjuntos al disco de copias.
 *
 * **Incremental y sin borrar nunca.** Un objeto se copia si falta en el destino
 * o si su tamaño no coincide; lo que ya está no se vuelve a subir, y lo que
 * desapareció del origen se queda en el destino. Esto último es el objetivo:
 * una evidencia borrada por error es justo lo que una copia tiene que poder
 * devolver.
 *
 * La base guarda la huella SHA-256 de cada fichero, así que lo que se comprueba
 * al verificar no es que el objeto esté, es que sea el mismo.
 */
final class EspejoDeObjetos
{
    public const PREFIJO = 'objetos';

    /**
     * @return array<string, array{copiados: int, presentes: int}>
     */
    public function sincronizar(): array
    {
        $destino = Storage::disk(config('copias.disco'));
        $resumen = [];

        /** @var list<string> $discos */
        $discos = config('copias.discos');

        foreach ($discos as $nombre) {
            $resumen[$nombre] = $this->sincronizarDisco(Storage::disk($nombre), $destino, $nombre);
        }

        return $resumen;
    }

    public static function rutaEnCopia(string $disco, string $ruta): string
    {
        return self::PREFIJO."/{$disco}/".ltrim($ruta, '/');
    }

    /**
     * @return array{copiados: int, presentes: int}
     */
    private function sincronizarDisco(Filesystem $origen, Filesystem $destino, string $nombre): array
    {
        $copiados = 0;
        $presentes = 0;

        foreach ($origen->allFiles() as $ruta) {
            $enCopia = self::rutaEnCopia($nombre, $ruta);

            if ($destino->exists($enCopia) && $destino->size($enCopia) === $origen->size($ruta)) {
                $presentes++;

                continue;
            }

            $flujo = $origen->readStream($ruta);

            try {
                $destino->writeStream($enCopia, $flujo);
            } finally {
                if (is_resource($flujo)) {
                    fclose($flujo);
                }
            }

            $copiados++;
        }

        return ['copiados' => $copiados, 'presentes' => $presentes];
    }
}
