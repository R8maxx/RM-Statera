<?php

declare(strict_types=1);

namespace App\Providers;

use App\Almacen\DiscoConEndpointPublico;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

/**
 * Los discos de S3, cuando conectar y firmar no son el mismo sitio.
 *
 * Intercepta el driver `s3` entero en vez de tocar la configuración de cada
 * disco: los tres discos de S3 pasan por aquí y **el que no declare
 * `endpoint_publico` —o lo declare igual que `endpoint`— sale exactamente como
 * salía antes**, con el disco que monta Laravel. En producción, donde los dos
 * endpoints coinciden, esta clase no llega a construir nada.
 *
 * El porqué del reparto está en `DiscoConEndpointPublico`, con el fallo concreto
 * que lo motivó.
 */
class AlmacenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * `Storage::extend('s3', …)` gana sobre `createS3Driver()` porque
         * `FilesystemManager::resolve()` mira `customCreators` primero. Dentro se
         * llama al constructor de Laravel directamente —es público— y no a
         * `resolve()`, así que no hay recursión.
         */
        Storage::extend('s3', function ($app, array $config): Filesystem {
            /** @var AwsS3V3Adapter $disco */
            $disco = Storage::createS3Driver($config);

            $publico = $config['endpoint_publico'] ?? null;

            if (! is_string($publico) || $publico === '' || $publico === ($config['endpoint'] ?? null)) {
                return $disco;
            }

            return DiscoConEndpointPublico::desde($disco, $config, $publico);
        });
    }
}
