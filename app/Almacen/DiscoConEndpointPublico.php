<?php

declare(strict_types=1);

namespace App\Almacen;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Arr;
use League\Flysystem\FilesystemAdapter as AdaptadorFlysystem;
use League\Flysystem\FilesystemOperator;

/**
 * Un disco S3 que CONECTA por un sitio y FIRMA por otro.
 *
 * Son dos preguntas distintas y durante un tiempo tuvieron la misma respuesta,
 * que es lo que dejó el módulo de documentos sin generar ni un PDF:
 *
 * - **La descarga de un fichero privado va por URL firmada, y en SigV4 el host
 *   es una cabecera firmada.** Reescribirlo después de firmar la invalida —eso
 *   es justo lo que hace la opción `temporary_url` de Laravel—, así que el
 *   nombre tiene que ser desde el principio el que el navegador vaya a resolver.
 *   En desarrollo es `minio.localhost`, porque los navegadores mandan cualquier
 *   `*.localhost` a loopback por su cuenta y ahí está publicado el 9000.
 * - **Pero el servidor no puede conectar por ese nombre.** libcurl, desde la
 *   7.77, resuelve internamente todo nombre terminado en `.localhost` a
 *   127.0.0.1 sin preguntar al resolutor. El alias de red de Docker estaba bien
 *   puesto —`getent hosts minio.localhost` devolvía la IP del contenedor— y curl
 *   ni lo consultaba: cada subida moría en 0 ms con «Connection refused», y
 *   dentro del contenedor 127.0.0.1:9000 es php-fpm. El síntoma no menciona ni a
 *   MinIO ni al DNS.
 *
 * En producción los dos endpoints suelen coincidir —o el público se deja vacío—
 * y entonces esta clase no llega a construirse: `AlmacenServiceProvider` devuelve
 * el disco de siempre.
 *
 * **Por qué una subclase y no `buildTemporaryUrlsUsing()`**, que es el hook que
 * Laravel documenta para esto: ese callback lo consulta
 * `FilesystemAdapter::temporaryUrl()`, y `AwsS3V3Adapter` **sobrescribe ese
 * método sin mirarlo**. Registrarlo compila, no avisa de nada y no se aplica
 * nunca — la clase de fallo silencioso que este producto evita a propósito.
 */
final class DiscoConEndpointPublico extends AwsS3V3Adapter
{
    /**
     * Sobre el disco que ya montó Laravel, con un segundo cliente para firmar.
     *
     * Es constructor con nombre y no código dentro del cierre de
     * `Storage::extend()` porque **ese cierre se reata al `FilesystemManager`**
     * (`bindCallbackToSelf`), así que ahí dentro no valen ni `$this` ni `self::`
     * y el fallo sale como un «Call to undefined method» sobre una clase de
     * Flysystem que no menciona nada de esto.
     *
     * @param  array<string, mixed>  $config
     */
    public static function desde(AwsS3V3Adapter $disco, array $config, string $endpointPublico): self
    {
        return new self(
            $disco->getDriver(),
            $disco->getAdapter(),
            $disco->getConfig(),
            $disco->getClient(),
            new S3Client(self::configuracionDeFirma($config, $endpointPublico)),
        );
    }

    /**
     * La misma configuración del disco con otro `endpoint`, montada como la monta
     * `FilesystemManager::formatS3Config()`, que es protegida.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function configuracionDeFirma(array $config, string $endpoint): array
    {
        $config['endpoint'] = $endpoint;
        $config += ['version' => 'latest'];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return Arr::except($config, ['token']);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  S3Client  $cliente  El que escribe y lee: endpoint interno.
     * @param  S3Client  $firmante  El que firma: endpoint público.
     */
    private function __construct(
        FilesystemOperator $driver,
        AdaptadorFlysystem $adapter,
        array $config,
        S3Client $cliente,
        private readonly S3Client $firmante,
    ) {
        parent::__construct($driver, $adapter, $config, $cliente);
    }

    /**
     * Calcado de `AwsS3V3Adapter::temporaryUrl()` salvo por el cliente, y sin la
     * rama de `temporary_url`: esa sustituye la base DESPUÉS de firmar, que es
     * exactamente lo que aquí no se puede hacer.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @param  array<string, mixed>  $options
     */
    public function temporaryUrl($path, $expiration, array $options = []): string
    {
        $comando = $this->firmante->getCommand('GetObject', array_merge([
            'Bucket' => $this->config['bucket'],
            'Key' => $this->prefixer->prefixPath($path),
        ], $options));

        return (string) $this->firmante
            ->createPresignedRequest($comando, $expiration, $options)
            ->getUri();
    }

    /**
     * Lo mismo para la subida directa. Hoy no la usa nadie —las evidencias suben
     * por el servidor—, pero dejarla firmando contra un host al que el navegador
     * no llega sería una mina puesta para quien la estrene.
     *
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @param  array<string, mixed>  $options
     * @return array{url: string, headers: array<string, mixed>}
     */
    public function temporaryUploadUrl($path, $expiration, array $options = []): array
    {
        $comando = $this->firmante->getCommand('PutObject', array_merge([
            'Bucket' => $this->config['bucket'],
            'Key' => $this->prefixer->prefixPath($path),
        ], $options));

        $peticion = $this->firmante->createPresignedRequest($comando, $expiration, $options);

        return [
            'url' => (string) $peticion->getUri(),
            'headers' => $peticion->getHeaders(),
        ];
    }

    public function providesTemporaryUrls(): bool
    {
        return true;
    }
}
