<?php

declare(strict_types=1);

use App\Almacen\DiscoConEndpointPublico;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

/**
 * Conectar y firmar no son el mismo sitio.
 *
 * El servidor escribe por un nombre de red interno y la URL temporal se firma
 * para el nombre por el que llega el navegador. **En SigV4 el host es cabecera
 * firmada**, así que no vale reescribirlo después —eso es lo que hace la opción
 * `temporary_url` de Laravel y por eso no se usa—: se firma directamente para
 * el nombre público.
 *
 * El fallo que lo motivó: con `minio.localhost` como endpoint de conexión,
 * **libcurl resuelve por su cuenta todo nombre terminado en `.localhost` a
 * 127.0.0.1** sin preguntar al resolutor, así que Docker resolvía bien el alias
 * y curl ni lo miraba. Toda subida a S3 moría en 0 ms con «Connection refused»
 * y el módulo de documentos llevaba cinco días sin generar un solo PDF.
 */
function discoDePrueba(?string $publico): string
{
    config()->set('filesystems.disks.s3_de_prueba', [
        'driver' => 's3',
        'key' => 'clave',
        'secret' => 'secreto',
        'region' => 'eu-west-1',
        'bucket' => 'cubo',
        'endpoint' => 'http://interno:9000',
        'endpoint_publico' => $publico,
        'use_path_style_endpoint' => true,
    ]);

    Storage::forgetDisk('s3_de_prueba');

    return 's3_de_prueba';
}

it('firma con el host público aunque conecte por el interno', function (): void {
    $disco = Storage::disk(discoDePrueba('http://publico.localhost:9000'));

    expect($disco)->toBeInstanceOf(DiscoConEndpointPublico::class);

    $url = $disco->temporaryUrl('carpeta/fichero.pdf', now()->addMinutes(5));

    expect($url)->toStartWith('http://publico.localhost:9000/cubo/carpeta/fichero.pdf')
        // Y la firma se calculó SOBRE ese host, no se sustituyó después: el host
        // va en las cabeceras firmadas, así que una sustitución posterior daría
        // «SignatureDoesNotMatch» en el navegador y en ningún test.
        ->toContain('X-Amz-SignedHeaders=host')
        ->not->toContain('interno:9000');
});

it('sin endpoint público, el disco es el de Laravel y nada cambia', function (): void {
    $disco = Storage::disk(discoDePrueba(null));

    expect($disco)->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($disco)->not->toBeInstanceOf(DiscoConEndpointPublico::class)
        ->and($disco->temporaryUrl('carpeta/fichero.pdf', now()->addMinutes(5)))
        ->toStartWith('http://interno:9000/cubo/carpeta/fichero.pdf');
});

it('con los dos endpoints iguales tampoco se monta nada', function (): void {
    expect(Storage::disk(discoDePrueba('http://interno:9000')))
        ->not->toBeInstanceOf(DiscoConEndpointPublico::class);
});

it('los discos de evidencias y documentos firman igual', function (): void {
    // Los dos guardan ficheros privados que se descargan por URL firmada; que
    // uno lo hiciera y el otro no es el fallo que nadie ve hasta producción.
    foreach (['documentos', 'evidencias'] as $nombre) {
        config()->set("filesystems.disks.{$nombre}.endpoint", 'http://interno:9000');
        config()->set("filesystems.disks.{$nombre}.endpoint_publico", 'http://publico.localhost:9000');
        Storage::forgetDisk($nombre);

        expect(Storage::disk($nombre))->toBeInstanceOf(DiscoConEndpointPublico::class);
    }
});
